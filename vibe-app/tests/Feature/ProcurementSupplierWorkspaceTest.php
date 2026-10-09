<?php
namespace Tests\Feature;
use App\Models\Organization; use App\Models\School; use App\Models\Supplier; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class ProcurementSupplierWorkspaceTest extends TestCase { use RefreshDatabase;
 public function test_supplier_workspace_searches_filters_and_paginates(): void { $o=Organization::create(['name'=>'Supplier Org','slug'=>'supplier-workspace','status'=>'active']); $s=School::create(['organization_id'=>$o->id,'code'=>'SUP','name'=>'Supplier School','status'=>'active']); $u=User::factory()->create(['organization_id'=>$o->id,'school_id'=>$s->id,'role'=>'school_admin']); foreach(range(1,21) as $i) Supplier::create(['organization_id'=>$o->id,'school_id'=>$s->id,'business_name'=>sprintf('Vendor %02d',$i),'tax_type'=>'vat','tax_rate'=>12,'status'=>$i===21?'inactive':'active']); $this->actingAs($u)->get(route('suppliers'))->assertOk()->assertSee('Supplier directory')->assertViewHas('suppliers',fn($v)=>$v->count()===20&&$v->total()===21); $this->actingAs($u)->get(route('suppliers',['search'=>'Vendor 21','status'=>'inactive']))->assertOk()->assertSee('Vendor 21')->assertDontSee('Vendor 20'); }
}
