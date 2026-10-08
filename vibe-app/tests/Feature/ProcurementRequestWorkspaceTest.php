<?php
namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementRequestWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_workspace_exposes_stage_sections_owner_and_next_action(): void
    {
        $organization=Organization::create(['name'=>'Alpha','slug'=>'alpha-request-workspace','status'=>'active']);
        $school=School::create(['organization_id'=>$organization->id,'code'=>'ARW','name'=>'Alpha School','status'=>'active']);
        $user=User::factory()->create(['organization_id'=>$organization->id,'school_id'=>$school->id,'role'=>'school_admin','name'=>'Maria Santos']);
        $request=ProcurementRequest::create(['organization_id'=>$organization->id,'school_id'=>$school->id,'requested_by'=>$user->id,'request_number'=>'PR-2026-0037','title'=>'Science laboratory supplies','amount'=>148750,'status'=>'approved']);
        ProcurementRequestItem::create(['organization_id'=>$organization->id,'procurement_request_id'=>$request->id,'name'=>'Microscope','quantity'=>2,'unit'=>'unit','unit_price'=>20000,'total'=>40000]);

        $response=$this->actingAs($user)->get(route('procurement.show',[$request,'section'=>'documents']));

        $response->assertOk()->assertSee('PR-2026-0037')->assertSee('Science laboratory supplies')->assertSee('Alpha School')->assertSee('Maria Santos')->assertSee('₱148,750.00')
            ->assertSee('Summary')->assertSee('Items')->assertSee('Documents')->assertSee('Activity')->assertSee('Create or review quotations')->assertSee('Request for Quotation');
        $this->assertSame(7, substr_count($response->getContent(),'class="civic-stage '));
        $response->assertSee('Canvass current stage',false);
    }
}
