<?php

namespace Tests\Feature;

use App\Models\CseCatalogItem;
use App\Services\CseCatalogLoader;
use App\Services\CseCatalogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\XlsxBuilder;
use Tests\TestCase;

class AppCseCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** A small Procurement Service style workbook: two categories, one item without a price, and a Part II row that must be ignored. */
    private function workbook(): string
    {
        return XlsxBuilder::make([['name' => 'Sheet1', 'cells' => [
            'A30' => 'Item & Specifications',
            'A32' => 'PART I. AVAILABLE AT PROCUREMENT SERVICE STORES',
            'A33' => 'Pesticides or Pest Repellents',
            'A34' => 1, 'B34' => '10191509-IN-A01', 'C34' => "INSECTICIDE,  aerosol type,\nnet content: 600ml min", 'D34' => 'can', 'Z34' => 139.36,
            'A35' => 'Solvents',
            'A36' => 2, 'B36' => '12191601-AL-E01', 'C36' => 'ALCOHOL, ethyl, 68%-70%', 'D36' => 'bottle', 'Z36' => 43.79,
            'A37' => 3, 'B37' => '99999999-NP-001', 'C37' => 'ITEM WITH NO PRICE', 'D37' => 'pc', 'Z37' => 0,
            'A38' => 'Motor Vehicles',
            'A39' => 'A. For the Exercise of the Executive Functions',
            'A40' => 4, 'B40' => '25101500-VH-01', 'C40' => 'SEDAN', 'D40' => 'unit',
            'A41' => 'PART II. OTHER ITEMS NOT AVALABLE AT PS BUT REGULARLY PURCHASED FROM OTHER SOURCES',
            'A42' => 1, 'B42' => 'X-1', 'C42' => 'Part II rows are not catalog items', 'D42' => 'pc', 'Z42' => 10,
        ]]]);
    }

    private function items(): array
    {
        return [
            ['group_heading' => null, 'category' => 'Solvents', 'code' => 'A-1', 'description' => 'Alcohol', 'unit' => 'bottle', 'price' => 43.79, 'sort_order' => 1],
            ['group_heading' => 'Motor Vehicles', 'category' => 'A. For the Exercise of the Executive Functions', 'code' => 'A-2', 'description' => 'Sedan', 'unit' => 'unit', 'price' => null, 'sort_order' => 2],
        ];
    }

    public function test_the_reader_reads_categories_items_units_and_prices(): void
    {
        $items = app(CseCatalogReader::class)->read($this->workbook());

        $this->assertSame([
            ['group_heading' => null, 'category' => 'Pesticides or Pest Repellents', 'code' => '10191509-IN-A01', 'description' => 'INSECTICIDE, aerosol type, net content: 600ml min', 'unit' => 'can', 'price' => 139.36, 'sort_order' => 1],
            ['group_heading' => null, 'category' => 'Solvents', 'code' => '12191601-AL-E01', 'description' => 'ALCOHOL, ethyl, 68%-70%', 'unit' => 'bottle', 'price' => 43.79, 'sort_order' => 2],
            ['group_heading' => null, 'category' => 'Solvents', 'code' => '99999999-NP-001', 'description' => 'ITEM WITH NO PRICE', 'unit' => 'pc', 'price' => null, 'sort_order' => 3],
            ['group_heading' => 'Motor Vehicles', 'category' => 'A. For the Exercise of the Executive Functions', 'code' => '25101500-VH-01', 'description' => 'SEDAN', 'unit' => 'unit', 'price' => null, 'sort_order' => 4],
        ], $items);
    }

    public function test_the_reader_refuses_a_workbook_without_part_i_or_without_items(): void
    {
        $noPartOne = XlsxBuilder::make([['name' => 'Sheet1', 'cells' => ['A1' => 'Some other sheet', 'B2' => 'x']]]);
        $noItems = XlsxBuilder::make([['name' => 'Sheet1', 'cells' => ['A32' => 'PART I. AVAILABLE AT PROCUREMENT SERVICE STORES', 'A33' => 'A category']]]);

        foreach ([$noPartOne, $noItems] as $path) {
            try {
                app(CseCatalogReader::class)->read($path);
                $this->fail('Expected a RuntimeException.');
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_the_loader_inserts_a_year_once_and_refuses_it_twice(): void
    {
        $this->assertSame(2, app(CseCatalogLoader::class)->load($this->items(), 2030));
        $this->assertSame(2, CseCatalogItem::where('catalog_year', 2030)->count());
        $this->assertNull(CseCatalogItem::where('catalog_year', 2030)->where('code', 'A-2')->value('price'));
        $this->assertSame('Motor Vehicles', CseCatalogItem::where('catalog_year', 2030)->where('code', 'A-2')->value('group_heading'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The 2030 catalog is already loaded.');
        app(CseCatalogLoader::class)->load($this->items(), 2030);
    }

    public function test_the_2026_catalog_is_loaded_by_the_migration(): void
    {
        $items = CseCatalogItem::where('catalog_year', 2026)->orderBy('sort_order')->get();

        $this->assertCount(368, $items);
        $first = $items->first();
        $this->assertSame('10191509-IN-A01', $first->code);
        $this->assertSame('Pesticides or Pest Repellents', $first->category);
        $this->assertSame('can', $first->unit);
        $this->assertSame('139.36', (string) $first->price);
        $this->assertTrue($items->contains(fn ($item) => $item->price === null), 'some items have no price in the source file');
        $this->assertSame(53, $items->where('group_heading', 'Motor Vehicles')->count(), 'the Motor Vehicles heading is kept above its sub-categories A to H');
        $this->assertNull($items->first()->group_heading);
        $this->assertSame(range(1, 368), $items->pluck('sort_order')->all());
        $this->assertTrue($items->every(fn ($item) => trim($item->code) !== '' && trim($item->description) !== '' && trim($item->unit) !== ''));
    }

    public function test_the_command_writes_json_from_a_workbook_and_loads_a_json_file(): void
    {
        $json = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cse-'.uniqid().'.json';

        $this->artisan('cse:import-catalog', ['file' => $this->workbook(), '--to-json' => $json])->assertSuccessful();
        $this->assertCount(4, json_decode(file_get_contents($json), true));
        $this->assertSame(0, CseCatalogItem::where('catalog_year', 2027)->count(), 'writing JSON loads nothing');

        $this->artisan('cse:import-catalog', ['file' => $json, '--year' => 2027])->assertSuccessful();
        $this->assertSame(4, CseCatalogItem::where('catalog_year', 2027)->count());

        $this->artisan('cse:import-catalog', ['file' => $json, '--year' => 2027])->assertFailed();
        $this->artisan('cse:import-catalog', ['file' => $json])->assertFailed();
        @unlink($json);
    }
}
