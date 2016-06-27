{if !empty($back_nav)}<div class="bkr_browsenav">{$back_nav}</div><br />{/if}
{if !empty($message)}<p class="pagetext pagemessage">{$message}</p><br />{/if}
<h3 class="pagetext">{$title}</h3><br />
<div class="pageinput">{$intro}</div><br />
{$tab_headers}
{$startform}
{$hidden}
{$start_basic_tab}
<div class="pageoverflow">
{foreach from=$basic item=entry}
 <p class="pagetext">{$entry.ttl}:{if isset($entry.mst)} *{/if}{if $entry.hlp}{$showtip}</p>{/if}
 <div class="pageinput">{$entry.inp}</div>
 {if $entry.hlp}<p class="pageinput help">{$entry.hlp}</p>{/if}
{/foreach}
</div>
{$end_tab}
{$start_adv_tab}
<div class="pageoverflow">
{foreach from=$advanced item=entry}
 <p class="pagetext">{$entry.ttl}:{if isset($entry.mst)} *{/if}{if $entry.hlp}{$showtip}{/if}</p>
 <div class="pageinput">{$entry.inp}</div>
 {if $entry.hlp}<p class="pageinput help">{$entry.hlp}</p>{/if}
{/foreach}
</div>
{$end_tab}
{$start_fmt_tab}
<div class="pageoverflow">
{foreach from=$formats item=entry}
 <p class="pagetext">{$entry.ttl}:{if isset($entry.mst)} *{/if}{if $entry.hlp}{$showtip}{/if}</p>
 <div class="pageinput">{$entry.inp}</div>
 {if $entry.hlp}<p class="pageinput help">{$entry.hlp}</p>{/if}
{/foreach}
</div>
{$end_tab}
{$tab_footers}
<div class="pageinput" style="margin-top:1em;">
{if $mod}{$submit} {/if}{$cancel}{if $mod} {$apply}{/if}
</div>
{$endform}
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
{if !empty($jsfuncs)}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
{/if}
