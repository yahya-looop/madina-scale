<?php
namespace App\Http\Controllers;
use App\Models\{CmsMedia,CmsBanner,CmsContentSection,CmsPage,CmsPageRevision};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage,DB};
use Illuminate\Validation\Rule;
class CmsContentController extends Controller {
 public function media(Request $r): JsonResponse {
  $items=CmsMedia::query()->latest()->paginate(min(max((int)$r->input('per_page',30),1),100));
  $items->getCollection()->transform(fn($m)=>array_merge($m->toArray(),['url'=>Storage::disk($m->disk)->url($m->path)]));
  return response()->json($items);
 }
 public function upload(Request $r): JsonResponse {
  $data=$r->validate(['file'=>['required','image','mimes:jpg,jpeg,png,webp,avif,gif','max:10240'],'alt_text'=>['nullable','string','max:255']]);
  $file=$r->file('file'); $path=$file->store('cms/media','public');
  $media=CmsMedia::create(['disk'=>'public','path'=>$path,'original_name'=>mb_substr($file->getClientOriginalName(),0,255),'mime_type'=>$file->getMimeType()??'application/octet-stream','size'=>$file->getSize(),'alt_text'=>$data['alt_text']??null,'uploaded_by'=>$r->user()->id]);
  return response()->json(['data'=>array_merge($media->toArray(),['url'=>Storage::disk('public')->url($path)])],201);
 }
 public function updateMedia(Request $r,CmsMedia $cmsMedium): JsonResponse {
  $data=$r->validate(['alt_text'=>['nullable','string','max:255']]); $cmsMedium->update($data); return response()->json(['data'=>$cmsMedium]);
 }
 public function deleteMedia(CmsMedia $cmsMedium): JsonResponse {
  $url=Storage::disk($cmsMedium->disk)->url($cmsMedium->path);
  $used=CmsPage::query()->where(fn($q)=>$q->where('featured_image',$url)->orWhere('featured_image',$cmsMedium->path))->exists()
   || CmsBanner::where('image_url',$url)->orWhere('image_url',$cmsMedium->path)->exists();
  abort_if($used,422,'This media file is currently used by a page or banner.');
  Storage::disk($cmsMedium->disk)->delete($cmsMedium->path); $cmsMedium->delete(); return response()->json(['message'=>'Media deleted.']);
 }
 public function publicBanners(Request $r): JsonResponse {
  $now=now();
  $items=CmsBanner::query()->where(fn($q)=>$q->where('status','active')->orWhere(fn($scheduled)=>$scheduled->where('status','scheduled')->whereNotNull('starts_at')->where('starts_at','<=',$now)))->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',$now))
   ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',$now))
   ->when($r->filled('placement'),fn($q)=>$q->where('placement',$r->string('placement')->toString()))
   ->orderBy('sort_order')->get(['id','title','image_url','alt_text','link_url','placement']);
  return response()->json(['data'=>$items]);
 }
 public function banners(Request $r): JsonResponse {
  return response()->json(CmsBanner::query()->when($r->filled('placement'),fn($q)=>$q->where('placement',$r->input('placement')))->when($r->filled('status'),fn($q)=>$q->where('status',$r->input('status')))->orderBy('sort_order')->latest()->paginate(100));
 }
 public function storeBanner(Request $r): JsonResponse { $b=CmsBanner::create($this->bannerData($r)+['created_by'=>$r->user()->id]); return response()->json(['data'=>$b],201); }
 public function updateBanner(Request $r,CmsBanner $cmsBanner): JsonResponse { $cmsBanner->update($this->bannerData($r)); return response()->json(['data'=>$cmsBanner->fresh()]); }
 public function deleteBanner(CmsBanner $cmsBanner): JsonResponse { $cmsBanner->delete(); return response()->json(['message'=>'Banner deleted.']); }
 private function bannerData(Request $r): array { return $r->validate([
  'title'=>['required','string','max:180'],'image_url'=>['required','string','max:2048'],'alt_text'=>['nullable','string','max:255'],
  'link_url'=>['nullable','url','max:2048'],'placement'=>['required','string','max:80'],'status'=>['required',Rule::in(['draft','active','scheduled','archived'])],
  'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after_or_equal:starts_at'],'sort_order'=>['required','integer','min:0','max:100000']
 ]); }
 public function sections(): JsonResponse { return response()->json(['data'=>CmsContentSection::query()->orderBy('name')->get()]); }
 public function storeSection(Request $r): JsonResponse {
  $d=$this->sectionData($r); $s=CmsContentSection::create($d+['created_by'=>$r->user()->id]); return response()->json(['data'=>$s],201);
 }
 public function updateSection(Request $r,CmsContentSection $cmsContentSection): JsonResponse { $cmsContentSection->update($this->sectionData($r)); return response()->json(['data'=>$cmsContentSection->fresh()]); }
 public function deleteSection(CmsContentSection $cmsContentSection): JsonResponse { $cmsContentSection->delete(); return response()->json(['message'=>'Section deleted.']); }
 private function sectionData(Request $r): array { return $r->validate([
  'name'=>['required','string','max:180'],'key'=>['required','string','max:190','alpha_dash',Rule::unique('cms_content_sections','key')->ignore($r->route('cmsContentSection')?->id)],
  'type'=>['required',Rule::in(['rich_text','hero','image_text','feature_grid','cta'])],'content'=>['required','array','max:40'],'is_active'=>['required','boolean']
 ]); }
 public function revisions(CmsPage $cmsPage): JsonResponse { return response()->json(['data'=>$cmsPage->revisions()->get(['id','cms_page_id','version','change_note','created_by','created_at'])]); }
 public function restore(Request $r,CmsPage $cmsPage,CmsPageRevision $revision): JsonResponse {
  abort_unless($revision->cms_page_id===$cmsPage->id,404);
  $snapshot=$revision->snapshot; $fields=['title','slug','excerpt','content','sections','featured_image','status','published_at','meta_title','meta_description','meta_keywords','canonical_url','robots_index','robots_follow'];
  $old=$cmsPage->only($fields); $next=array_intersect_key($snapshot,array_flip($fields));
  DB::transaction(function()use($cmsPage,$r,$old,$next):void {
   $version=((int)$cmsPage->revisions()->max('version'))+1;
   CmsPageRevision::create(['cms_page_id'=>$cmsPage->id,'version'=>$version,'snapshot'=>$old,'created_by'=>$r->user()->id,'change_note'=>'Before restoring an earlier revision']);
   $next['updated_by']=$r->user()->id; $cmsPage->update($next);
   CmsPageRevision::create(['cms_page_id'=>$cmsPage->id,'version'=>$version+1,'snapshot'=>$cmsPage->fresh()->only(array_keys($old)),'created_by'=>$r->user()->id,'change_note'=>'Restored revision']);
  });
  return response()->json(['data'=>$cmsPage->fresh()]);
 }
}
