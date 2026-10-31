<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\LocationInventoryAccessService;
use Illuminate\Database\Query\Builder;
use Inertia\Inertia;
class BusinessAnalyticsController extends Controller {
 public function __construct(private readonly LocationInventoryAccessService $locations) {}
 public function page(Request $r,string $view='overview') { abort_unless($r->user()?->can('system.analytics.view'),403); return Inertia::render('Analytics/Index',['view'=>$view]); }
 public function data(Request $r) {
  $d=$r->validate(['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from'],'location_id'=>['nullable','integer','exists:locations,id']]); $from=$d['from']??now()->startOfMonth()->toDateString(); $to=$d['to']??now()->toDateString();
  $orders=DB::table('sales_orders')->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59']);
  $locationId=isset($d['location_id'])?(int)$d['location_id']:null; if($locationId) $this->locations->assertView($r->user(),$locationId); $this->scope($orders,$r->user(),$locationId,'sales_orders.location_id');
  $valid=clone $orders; $valid->whereNotIn('status',['cancelled','canceled','rejected']);
  $orderCount=(clone $valid)->count(); $revenue=(float)(clone $valid)->sum('grand_total');
  $daily=(clone $valid)->selectRaw('DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as revenue')->groupByRaw('DATE(created_at)')->orderByRaw('DATE(created_at)')->get();
  $status=(clone $orders)->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->orderByDesc('count')->get();
  $customers=DB::table('customers')->count();
  $campaigns=DB::table('marketing_campaigns')->count(); $campaignEvents=DB::table('marketing_campaign_events')->whereBetween('occurred_at',[$from.' 00:00:00',$to.' 23:59:59']);
  $events=(clone $campaignEvents)->select('event_type')->selectRaw('COUNT(*) as total')->groupBy('event_type')->pluck('total','event_type');
  $revenueByCampaign=(clone $campaignEvents)->where('event_type','conversion')->sum('value');
  return response()->json(['range'=>['from'=>$from,'to'=>$to],'summary'=>['revenue'=>$revenue,'orders'=>$orderCount,'customers'=>$customers,'average_order_value'=>$orderCount?round($revenue/$orderCount,2):0,'campaigns'=>$campaigns,'campaign_impressions'=>(int)($events['impression']??0),'campaign_clicks'=>(int)($events['click']??0),'campaign_conversions'=>(int)($events['conversion']??0),'tracked_campaign_conversion_value'=>(float)$revenueByCampaign],'daily'=>$daily,'order_status'=>$status]);
 }
 private function scope(Builder $query, User $user, ?int $locationId, string $column): void { if($locationId) $query->where($column,$locationId); else $this->locations->scopeViewable($query,$user,$column); }
}
