{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
<div id="needjs">{$needjs}</div>
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}: {$textwhat}</h4>
{if isset($desc)}<p class="bkgdesc">{$desc}</p><br /><br />{/if}
{if isset($pictures)}<div class="bkgimg">
{foreach from=$pictures item=pic}
<img src="{$pic->url}"{if !empty($pic->title)} alt="{$pic->title}"{/if} />
{/foreach}
</div><br /><br />{/if}
{if isset($membermsg)}<p>{$membermsg}</p>{/if}
{if isset($currentmsg)}<p>{$currentmsg}</p>{/if}
<p>{$mustmsg}</p>
{$startform}
{$hidden}
<div style="overflow:auto;">
<table class="shrink"><tbody>
{foreach from=$tablerows item=entry}
<tr{if $entry->class} class="{$entry->class}"{/if}><td>{if $entry->mst}* {/if}{$entry->ttl}</td><td>{$entry->inp}</td></tr>
{/foreach}
</tbody></table>
</div>
<br />
{$submit}{if isset($cart)} {$cart}{/if} {$cancel} {if isset($choose)} {$choose}{/if}
{$endform}
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
