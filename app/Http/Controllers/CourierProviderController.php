<?php

namespace App\Http\Controllers;

use App\Models\CourierProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourierProviderController extends Controller
{
    private const ZONES = ['inside_dhaka','outside_dhaka','remote_special'];
    private const CODES = ['steadfast','carrybee','bangladesh_post','pathao','redx'];

    public function page(Request $request): Response
    {
        abort_unless($request->user()->can('system.shipping.view'), 403);
        return Inertia::render('Shipping/CourierProviders', [
            'canManage' => $request->user()->can('system.shipping.update'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.shipping.view'), 403);
        $providers = CourierProvider::query()->with(['rates'=>fn($q)=>$q->orderBy('sort_order')->orderBy('id')])->orderBy('priority')->orderBy('name')->get();
        return response()->json(['data'=>$providers->map(fn($p)=>[
            'id'=>$p->id,'code'=>$p->code,'name'=>$p->name,'description'=>$p->description,'logo_url'=>$p->logo_url,
            'is_enabled'=>$p->is_enabled,'priority'=>$p->priority,'environment'=>$p->environment,'capabilities'=>$p->capabilities ?? [],
            'service_zones'=>$p->service_zones ?? self::ZONES,'has_credentials'=>!empty($p->credentials),'health_status'=>$p->health_status,
            'last_health_check_at'=>$p->last_health_check_at,'rates'=>$p->rates,
        ]),'zones'=>self::ZONES]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.shipping.update'), 403);
        $data=$request->validate([
            'code'=>['required',Rule::in(self::CODES),'unique:courier_providers,code'],'name'=>['required','string','max:120'],
            'description'=>['nullable','string','max:2000'],'logo_url'=>['nullable','url','max:2048'],'is_enabled'=>['boolean'],
            'priority'=>['required','integer','min:1','max:65535'],'environment'=>['required',Rule::in(['sandbox','production'])],
            'capabilities'=>['array'],'capabilities.*'=>['string',Rule::in(['quote','booking','tracking','cod','webhook'])],
            'service_zones'=>['array'],'service_zones.*'=>[Rule::in(self::ZONES)],'credentials'=>['nullable','array'],
            'settings'=>['nullable','array'],'rates'=>['array'],'rates.*.zone_code'=>['required',Rule::in(self::ZONES)],
            'rates.*.min_weight_grams'=>['required','integer','min:0'],'rates.*.max_weight_grams'=>['nullable','integer','gt:rates.*.min_weight_grams'],
            'rates.*.base_charge'=>['required','numeric','min:0'],'rates.*.additional_per_kg'=>['nullable','numeric','min:0'],
            'rates.*.cod_charge_percent'=>['nullable','numeric','min:0','max:100'],'rates.*.is_enabled'=>['boolean'],
        ]);
        $provider=DB::transaction(function() use($data){
            $provider=CourierProvider::create(collect($data)->except('rates')->all());
            foreach($data['rates']??[] as $rate) $provider->rates()->create($rate);
            DB::table('courier_provider_audits')->insert(['courier_provider_id'=>$provider->id,'actor_id'=>auth()->id(),'action'=>'created','changes'=>json_encode(['code'=>$provider->code,'is_enabled'=>$provider->is_enabled,'priority'=>$provider->priority]),'created_at'=>now()]);
            return $provider->load('rates');
        });
        return response()->json(['message'=>'Courier provider created.','data'=>$provider],201);
    }

    public function update(Request $request, CourierProvider $provider): JsonResponse
    {
        abort_unless($request->user()->can('system.shipping.update'), 403);
        $data=$request->validate([
            'name'=>['sometimes','required','string','max:120'],'description'=>['nullable','string','max:2000'],
            'logo_url'=>['nullable','url','max:2048'],'is_enabled'=>['sometimes','boolean'],'priority'=>['sometimes','integer','min:1','max:65535'],
            'environment'=>['sometimes',Rule::in(['sandbox','production'])],'capabilities'=>['sometimes','array'],
            'capabilities.*'=>['string',Rule::in(['quote','booking','tracking','cod','webhook'])],'service_zones'=>['sometimes','array'],
            'service_zones.*'=>[Rule::in(self::ZONES)],'credentials'=>['sometimes','nullable','array'],'settings'=>['sometimes','nullable','array'],
            'rates'=>['sometimes','array'],'rates.*.zone_code'=>['required',Rule::in(self::ZONES)],'rates.*.min_weight_grams'=>['required','integer','min:0'],
            'rates.*.max_weight_grams'=>['nullable','integer','gt:rates.*.min_weight_grams'],'rates.*.base_charge'=>['required','numeric','min:0'],
            'rates.*.additional_per_kg'=>['nullable','numeric','min:0'],'rates.*.cod_charge_percent'=>['nullable','numeric','min:0','max:100'],'rates.*.is_enabled'=>['boolean'],
        ]);
        DB::transaction(function() use($provider,$data){
            $rates=$data['rates']??null; unset($data['rates']);
            if(array_key_exists('credentials',$data) && empty($data['credentials'])) unset($data['credentials']);
            $provider->update($data);
            if($rates!==null){$provider->rates()->delete(); foreach($rates as $rate)$provider->rates()->create($rate);}
            DB::table('courier_provider_audits')->insert(['courier_provider_id'=>$provider->id,'actor_id'=>auth()->id(),'action'=>'updated','changes'=>json_encode(collect($data)->except(['credentials'])->all()),'created_at'=>now()]);
        });
        return response()->json(['message'=>'Courier provider updated.','data'=>$provider->fresh()->load('rates')]);
    }

    public function destroy(Request $request, CourierProvider $provider): JsonResponse
    {
        abort_unless($request->user()->can('system.shipping.update'), 403);
        abort_if($provider->rates()->exists(), 422, 'Remove its rate rules before deleting this provider.');
        DB::table('courier_provider_audits')->insert(['courier_provider_id'=>$provider->id,'actor_id'=>$request->user()->id,'action'=>'deleted','changes'=>json_encode(['code'=>$provider->code,'name'=>$provider->name]),'created_at'=>now()]);
        $provider->delete(); return response()->json(['message'=>'Courier provider deleted.']);
    }
}
