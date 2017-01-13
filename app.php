<?php 
require_once('tcpdf/tcpdf.php');
require_once('fpdi/fpdi.php');

if (! $_POST) {echo "400 Bad Request"; die();} session_start();
if(isset($_FILES['file']) && isset($_FILES['cert'])) {
	$file_name = $_FILES['file']['name'];
	$cert_name = $_FILES['cert']['name'];
	
	$file_tmp = $_FILES['file']['tmp_name'];
	$cert_tmp = $_FILES['cert']['tmp_name'];
 	
 	$file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
 	$cert_ext = pathinfo($cert_name, PATHINFO_EXTENSION);

 	$multiple = false;
 	foreach ($_SESSION['dataUpload'] as $value) {
 		if(strpos($value, substr($file_name, 0, -13)) !== false) {
 			$multiple = true;
 			$_SESSION['valid'] = 'There exists a file with same name';
 		}
 	}

 	move_uploaded_file($cert_tmp, ("./tmp/" . $cert_name));
 	move_uploaded_file($file_tmp, ("./tmp/" . $file_name));

 	if($cert_ext == "p12") {
		if($_POST['submit'] == "sign" && !$multiple) {
		 	// initiate PDF
			$pdf = new FPDI();

			// set the source file
			$pageCount = $pdf->setSourceFile("./tmp/" . $file_name);

			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
			    // import a page
			    $templateId = $pdf->importPage($pageNo);
			    // get the size of the imported page
			    $size = $pdf->getTemplateSize($templateId);

			    // create a page (landscape or portrait depending on the imported page size)
			    if ($size['w'] > $size['h']) {
			        $pdf->AddPage('L', array($size['w'], $size['h']));
			    } else {
			        $pdf->AddPage('P', array($size['w'], $size['h']));
			    }

			    // use the imported page
			    $pdf->useTemplate($templateId);
			}

			// Get the public key in crt format
			exec('openssl pkcs12 -in "./tmp/' . $cert_name . '" -out "./tmp/' . substr($cert_name, 0, -4) . '.crt" -clcerts -nokeys -passin pass:' . $_POST['pass']);

			$p12File = file_get_contents("./tmp/" . $cert_name);
			$certificate = file_get_contents("./tmp/" . substr($cert_name, 0, -4) . ".crt");
			openssl_pkcs12_read($p12File, $certs, $_POST['pass']);

			// set document signature
			$pdf->setSignature($certificate, $certs['pkey'], $_POST['pass'], '', 2, array());

			// output the signed file
			$file_name = substr($file_name, 0, -4) . ' - Signed.pdf';
			$pdf->Output(dirname(__FILE__) . '/signed/' . $file_name, 'F');

	 		$tmp = "key" . $_SESSION['idx'];
	 		$_SESSION['dataUpload'][$tmp] = "<tr><td>" . substr($file_name, 0, -13) . "</td><td style='text-align: center;'><form action=\"app.php\" method=\"post\" enctype=\"multipart/form-data\"><input type=\"hidden\" value=\"" . $file_name . "\" name=\"hapus\"/><input type=\"hidden\" value=\"" . $_SESSION['idx'] . "\" name=\"idxHapus\"/><input type=\"Submit\" value=\"Delete\" name=\"submit\"/></form></td><td style='text-align: center;'><form action=\"app.php\" method=\"post\" enctype=\"multipart/form-data\"><input type=\"hidden\" value=\"" . $file_name . "\" name=\"download\"/><input type=\"submit\" value=\"Download\" name=\"submit\"/></form></td>";
	 		$_SESSION['idx'] += 1;
	 		$_SESSION['valid'] = 'Sign Success!';
	 	}
	} else {
		$_SESSION['valid'] = "Unsupported certificate format";
	}

	unlink("./tmp/" . substr($cert_name, 0, -4) . ".crt");
 	unlink("./tmp/" . $cert_name);
 	unlink("./tmp/" . $file_name);
 	header("location: index.php");
}

if(isset($_POST['hapus'])) {
	unlink("./signed/" . $_POST['hapus']);
	$tmp = "key" . $_POST['idxHapus'];
	unset($_SESSION['dataUpload'][$tmp]);
	header("location: index.php");
}

if(isset($_POST['download'])) {
	header("Content-Type: application/pdf");
	header("Content-Transfer-Encoding: Binary");
	header("content-disposition: attachment; filename=\"" . $_POST['download'] . "\"");
	readfile(dirname(__FILE__) . "/signed/" . $_POST['download']);
	header("location: index.php");
}