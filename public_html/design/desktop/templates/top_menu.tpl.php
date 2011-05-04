<div class="header">
  <ul class="menu">
  <li class="<?php echo ( (strpos($uri,'/servers/')!==false) ? 'active ' : '' ); ?>first"><a href="/servers/">Servers</a></li>
    <li<?php echo ( (strpos($uri,'/domains/' )!==false) ? ' class="active"' : '' ); ?>><a href="/domains/">Domains</a></li>
    <li<?php echo ( (strpos($uri,'/accounts/')!==false) ? ' class="active"' : '' ); ?>><a href="/accounts/">Accounts to Domains</a></li>
    <li<?php echo ( (strpos($uri,'/search/'  )!==false) ? ' class="active"' : '' ); ?>><a href="/search/">Search</a></li>
    <li class="<?php echo ( (strpos($uri,'/cleanup/')!==false) ? 'active ' : '' ); ?>last"><a href="/cleanup/">Cleanup</a></li>
  </ul>
  <br class="clr"/>
</div>
<br/>
