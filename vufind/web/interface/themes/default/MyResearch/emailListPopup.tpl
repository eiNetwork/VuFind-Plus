<div align="left">
  {if $message}<div class="error">{$message|translate}</div>{/if}

  <form action="{$url}/MyResearch/EmailList" method="post" onSubmit='SendMyListEmail(this.elements[&quot;to&quot;].value, 
    this.elements[&quot;from&quot;].value, this.elements[&quot;message&quot;].value,this.elements[&quot;listId&quot;].value
    {* Pass translated strings to Javascript -- ugly but necessary: *}
    {literal}{{/literal}sending: &quot;{translate text='email_sending'}&quot;, 
     success: &quot;{translate text='email_success'}&quot;,
     failure: &quot;{translate text='email_failure'}&quot;{literal}}{/literal}
    ); return false;'>
    <input type="hidden" name="listId" value="{$listId|escape}">
    <b>{translate text='To'}:</b><br />
    <input type="text" name="to" size="40"><br />
    <b>{translate text='From'}:</b><br />
    <input type="text" name="from" size="40"><br />
    <b>{translate text='Message'}:</b><br />
    <textarea name="message" rows="3" cols="40"></textarea><br />
    <input type="submit" name="submit" value="{translate text='Send'}">
  </form>
</div>