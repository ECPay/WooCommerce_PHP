<?php
header("Content-Type:text/html; charset=utf-8");

$CVSStoreName = $_REQUEST['CVSStoreName'];
$CVSAddress   = $_REQUEST['CVSAddress'];
$CVSTelephone = $_REQUEST['CVSTelephone'];
$CVSStoreID   = $_REQUEST['CVSStoreID'];

?>
<script type="text/javascript">
<!--
window.opener.document.getElementById("_billing_purchaserStore").value   = "<?php echo $CVSStoreName;?>";
window.opener.document.getElementById("_billing_purchaserAddress").value = "<?php echo $CVSAddress;?>";
window.opener.document.getElementById("_billing_purchaserPhone").value   = "<?php echo $CVSTelephone;?>";
window.opener.document.getElementById("_billing_CVSStoreID").value       = "<?php echo $CVSStoreID;?>";
alert('門市變更完成，請儲存訂單');
window.close();
//-->
</script> 