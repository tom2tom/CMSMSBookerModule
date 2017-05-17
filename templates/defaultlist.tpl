<div id="needjs">{$needjs}</div>
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}</h4>
{if isset($desc)}<p class="bkgdesc">{$desc}</p>{/if}
{if isset($pictures)}<div class="bkgimg">
{foreach from=$pictures item=pic}
<img src="{$pic->url}"{if !empty($pic->ttl)} alt="{$pic->ttl}"{/if} />
{/foreach}
</div><br />{/if}
{if !empty($bulletin)}<p>{$bulletin}</p><br /><br />{/if}
{$startform}
{foreach from=$hidden item=inc}{$inc}{/foreach}
{if isset($actions)}{foreach from=$actions key=k item=btn}{if $k>0}&nbsp;&nbsp;{/if}{$btn}{/foreach}<br /><br />{/if}
{if $sections}
<div class="bkglist" style="oveflow:auto;">
 <table class="booker">
  <tbody>
{foreach $sections as $block}{cycle values="2,1,0" assign=nc}
{if $nc==2}<tr>{/if}
 <td class="list">
{if !empty($block->ttl)}<h5>{$block->ttl}</h5>{/if}
{foreach from=$block->rows item=text}<p>&nbsp;&nbsp;{$text}</p>{/foreach}
 </td>
{if !$nc}</tr>{/if}
{/foreach}
{if $nc>0}{for $foo=1 to $nc}<td class="list"></td>{/for}</tr>{/if}
  </tbody>
 </table>
</div>
{else}
<p>{$nobookings}</p>
{/if}
<div>
<fieldset>
<legend>{$actionstitle}</legend>
<table id="bookactions" style="display:inline-block;border:0;overflow:auto;">
<tr>{foreach from=$actions1 item=inc}<td>{$inc}</td>{/foreach}</tr>
<tr>{foreach from=$actions2 item=inc}<td>{$inc}</td>{/foreach}</tr>
</table>
</fieldset>
<p style="margin-left:5px;">{$book} {$cart}</p>
</div>
{$endform}
