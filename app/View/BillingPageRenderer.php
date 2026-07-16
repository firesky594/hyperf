<?php
declare(strict_types=1);namespace App\View;
/** 渲染采购账单、付款凭证、后台确认和供应商结算页面。 */
final class BillingPageRenderer
{
 /**
  * 处理采购方账单列表。
  *
  * @param array $s 当前登录会话数据。
  * @param array $r 待渲染或转换的数据列表。
  * @param string $c 当前操作使用的 CSRF 令牌。
  * @return string 返回采购方账单列表字符串结果。
  */
 public function buyerInvoices(array$s,array$r,string$c):string{$body=$this->rows($r,'当前没有账单。');foreach($r as$row)$body.='<p><a href="/workspace/buyer/billing/proof?invoice_id='.(int)($row['id']??0).'">为账单 #'.(int)($row['id']??0).' 提交付款凭证</a></p>';return$this->shell('采购账单','<h1>采购账单</h1>'.$body);}
 /**
  * 处理付款付款凭证。
  *
  * @param array $s 当前登录会话数据。
  * @param int $id 标识数值。
  * @param string $c 当前操作使用的 CSRF 令牌。
  * @return string 返回付款付款凭证字符串结果。
  */
 public function paymentProof(array$s,int$id,string$c):string{return$this->shell('付款凭证','<h1>上传付款凭证</h1><form action="/workspace/buyer/billing/proof" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="'.$this->e($c).'"><input type="hidden" name="invoice_id" value="'.$id.'"><label>付款参考号<input name="reference_no" required></label><label>付款凭证（PDF/JPEG/PNG，最大 5 MB）<input type="file" name="proof" accept="application/pdf,image/jpeg,image/png" required></label><button>提交凭证</button></form>');}
 /**
  * 处理管理员Confirmations。
  *
  * @param array $r 待渲染或转换的数据列表。
  * @param string $c 当前操作使用的 CSRF 令牌。
  * @return string 返回管理员Confirmations字符串结果。
  */
 public function adminConfirmations(array$r,string$c):string{$body=$this->rows($r,'当前没有待确认付款。');foreach($r as$row)$body.='<form action="/agent_admin/billing/payment-confirm" method="post"><input type="hidden" name="_csrf" value="'.$this->e($c).'"><input type="hidden" name="proof_id" value="'.(int)($row['id']??0).'"><button>确认这笔付款</button></form>';return$this->shell('付款确认','<h1>付款确认与结算</h1>'.$body.'<form action="/agent_admin/billing/commission" method="post"><input type="hidden" name="_csrf" value="'.$this->e($c).'"><label>供应商 ID<input name="supplier_profile_id" required></label><label>佣金基点<input name="commission_bps" required></label><button>保存佣金率</button></form><form action="/agent_admin/billing/settlement-confirm" method="post"><input type="hidden" name="_csrf" value="'.$this->e($c).'"><label>结算单 ID<input name="settlement_id" required></label><button>确认结算</button></form>');}
 /**
  * 处理供应商结算列表。
  *
  * @param array $s 当前登录会话数据。
  * @param array $r 待渲染或转换的数据列表。
  * @return string 返回供应商结算列表字符串结果。
  */
 public function supplierSettlements(array$s,array$r):string{return$this->shell('供应商结算','<h1>供应商结算</h1>'.$this->rows($r,'当前没有结算单。'));}
 /**
  * 把数据库查询结果统一转换为数组列表。
  *
  * @param array $r 待渲染或转换的数据列表。
  * @param string $empty 数据为空时显示的提示文本。
  * @return string 返回结果列表字符串结果。
  */
 private function rows(array$r,string$empty):string{if($r===[])return'<p>'.$empty.'</p>';$html='';foreach($r as$row)$html.='<pre>'.$this->e(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).'</pre>';return$html;}/**
 * 转义 HTML 特殊字符，防止页面注入。
 *
 * @param string $v 待转义或处理的字符串值。
 * @return string 返回e字符串结果。
 */
private function e(string$v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}/**
 * 组装包含公共结构和样式的完整页面。
 *
 * @param string $t 页面标题。
 * @param string $b 页面主体 HTML。
 * @return string 返回shell字符串结果。
 */
private function shell(string$t,string$b):string{return'<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$t.' · UniAPI</title><style>body{background:#071012;color:#eef8f5;padding:6vw;font:16px system-ui}main{max-width:980px;margin:auto}h1{font-size:clamp(40px,8vw,72px)}form{display:grid;gap:14px;margin:20px 0;padding:20px;border:1px solid #294642}input,button{padding:12px;background:#10201e;color:#fff;border:1px solid #45e0c3}pre{white-space:pre-wrap}</style></head><body><main>'.$b.'</main></body></html>';}
}
