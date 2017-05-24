
<?php
header("Content-Type:text/html; charset=utf-8");

$CVSStoreName = $_REQUEST['CVSStoreName'];
$CVSAddress   = $_REQUEST['CVSAddress'];
$CVSTelephone = $_REQUEST['CVSTelephone'];
$CVSStoreID   = $_REQUEST['CVSStoreID'];

?>

<script type="text/javascript">
<!--

window.opener.document.getElementById("purchaserStore").value   = "<?php echo $CVSStoreName;?>";
window.opener.document.getElementById("purchaserAddress").value = "<?php echo $CVSAddress;?>";
window.opener.document.getElementById("purchaserPhone").value   = "<?php echo $CVSTelephone;?>";
window.opener.document.getElementById("CVSStoreID").value       = "<?php echo $CVSStoreID;?>";
window.close();

//-->
</script> 