<?php
namespace App\Modules\Customer\Actions;
use App\Modules\Customer\Models\{Customer,CustomerGroup,CustomerImportBatch,CustomerImportRow};
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

final class StageCustomerImportAction {
 public const FIELDS=['first_name_ar','last_name_ar','first_name_en','last_name_en','phone','email','customer_group','consent_purpose','consent_status'];
 /** @return list<array<string, string>> */
 public static function readSpreadsheet(string $path): array {
  $reader=ReaderFactory::createFromFile($path); $reader->open($path); $headers=null; $rows=[]; $number=0;
  try { foreach($reader->getSheetIterator() as $sheet){foreach($sheet->getRowIterator() as $row){$number++;$cells=$row->getCells();$values=array_map(fn($cell)=>(string)($cell->getValue() ?? ''),$cells);if($number===1){$headers=array_map(fn($value)=>strtolower(trim($value)),$values);if($headers!==self::FIELDS)throw new InvalidArgumentException(__('The customer template headers must match the configured template exactly.'));continue;}if($number>5001)throw new InvalidArgumentException(__('The import is limited to 5,000 rows.'));if(!array_filter($values,fn($value)=>trim($value)!==''))continue;foreach($cells as $index=>$cell)if($cell instanceof FormulaCell||(($headers[$index]??'')!=='phone'&&preg_match('/^[=+\-@]/',ltrim((string)$cell->getValue()))))throw new InvalidArgumentException(__('Formula-like cell values are not accepted in customer imports.'));$rows[]=array_combine($headers,array_slice(array_pad($values,count($headers),''),0,count($headers)));}break;}return $rows;}finally{$reader->close();}
 }
 /** @param list<array<string, string|int>> $rows @return list<array{raw:array<string,string|int>,errors:list<string>}> */
 public static function validateRows(array $rows,string $mode='create_only',?Store $store=null): array {
  $seen=[]; return array_map(function(array $raw)use(&$seen,$mode,$store):array{$errors=[];foreach(['first_name_ar','last_name_ar','phone','consent_purpose','consent_status'] as $field)if(trim((string)($raw[$field]??''))==='')$errors[]="$field is required";if(($raw['consent_status']??'')!=='granted')$errors[]='Consent must be granted';try{$phone=PhoneNormalizer::normalize((string)($raw['phone']??''));}catch(InvalidArgumentException $exception){$phone='';$errors[]=$exception->getMessage();}if($phone!==''&&isset($seen[$phone]))$errors[]='Duplicate phone in this import batch';if($phone!==''){$seen[$phone]=true;$customer=Customer::query()->where('phone_normalized',$phone)->where('status','active')->first();if($mode==='update_existing'){if($customer===null)$errors[]='No active customer matches this phone for Update Existing.';else $raw['customer_id']=$customer->id;}elseif($customer!==null)$errors[]='Duplicate phone requires review';}$groupName=trim((string)($raw['customer_group']??''));if($groupName!==''){if($store===null)$errors[]='Customer group imports require a configured stable group code.';else{$groups=CustomerGroup::query()->forCompany((int)$store->company_id)->active()->where(fn($query)=>$query->where('name_ar',$groupName)->orWhere('name_en',$groupName))->limit(2)->get(['id']);if($groups->count()===1)$raw['customer_group_id']=$groups->first()->id;else $errors[]=$groups->isEmpty()?'Customer group is missing or inactive in this company.':'Customer group name is ambiguous in this company.';}}return ['raw'=>$raw,'errors'=>array_values(array_unique($errors))];},$rows);
 }
 public function stage(array $rows,string $filename,string $mode,int $userId,Store $store):CustomerImportBatch { Gate::forUser(auth()->user())->authorize($mode==='update_existing'?'customers.edit':'customers.create'); if(!in_array($mode,['create_only','update_existing'],true)) throw new InvalidArgumentException('Unsupported import mode.'); return DB::transaction(function()use($rows,$filename,$mode,$userId,$store){$b=CustomerImportBatch::create(['created_by'=>$userId,'original_filename'=>$filename,'mode'=>$mode,'status'=>'ready_for_review','total_rows'=>count($rows),'headers'=>self::FIELDS]);$valid=0;$invalid=0;foreach(self::validateRows($rows,$mode,$store) as $i=>$row){$errors=$row['errors'];$status=$errors?'invalid':'valid';$b->rows()->create(['row_number'=>$i+2,'raw_data'=>$row['raw'],'mapped_data'=>$row['raw'],'errors'=>$errors,'status'=>$status]);$errors?$invalid++:$valid++;}$b->update(['valid_rows'=>$valid,'invalid_rows'=>$invalid]);app(RecordAuditEvent::class)->execute('customer_import','stage_customer_import',$b,after:$b->only(['id','mode','status','total_rows','valid_rows','invalid_rows']));return $b;}); }
}
