<iframe id="iframe" name="iframe" hidden></iframe>
<form id="form" action="<?php echo $_GET['RequestURL'] ?>" method="post" target="iframe" hidden>
	<input name="pveauth" value="<?php echo $_GET['PVEAuth'] ?>">
	<input name="redurl" value="<?php echo $_GET['RedirectURL'] ?>">
</form>
<script>
	document.getElementById("form").submit()
	document.getElementById("iframe").onload=()=>{location='<?php echo $_GET['RedirectURL'] ?>'};
</script>
<code style="color:white">CONNECTING TO THE SERVER, PLEASE WAIT ...</code>