<?php

header('Content-Type: text/plain; charset=utf-8');

include 'Signature.php';
include 'symmetric_enc.php';
include "generateUrl.php";

session_start();

try {
   /* $baseKey = $_POST["baseKey"];
    if($baseKey == "" )
        throw new RuntimeException("<script>alert('请填写分享码！'); history.go(-1);</script>");*/

    if (
        !isset($_FILES['upload_file']['error']) ||
        is_array($_FILES['upload_file']['error'])
    ) {
        throw new RuntimeException('Invalid parameters.');
    }

    // Check $_FILES['upfile']['error'] value.
    switch ($_FILES['upload_file']['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    // You should also check filesize here.
    if ($_FILES['upload_file']['size'] > 10485760) {
        throw new RuntimeException('文件必须小于10MB');
    }

    // DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
    // Check MIME Type by yourself.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (false === $ext = array_search(
        $finfo->file($_FILES['upload_file']['tmp_name']),
        array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
	    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	    'xls' => 'application/vnd.ms-excel',
	    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	    'pdf' => 'application/pdf',
	    'ppt' => 'application/vnd.ms-powerpoint',
	    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ),
        true
    )) {
        throw new RuntimeException('文件类型不合法！');
    }

    // You should name it uniquely.
    // DO NOT USE $_FILES['upfile']['name'] WITHOUT ANY VALIDATION !!
    // On this example, obtain safe unique name from its binary data.
    $fileHash = sha1_file($_FILES["upload_file"]["tmp_name"]);
    $name = $_FILES['upload_file']['name'];
    $user = $_SESSION['islogin'];
    $cwd = dirname(__FILE__);
    $url = DownloadUrl("$fileHash.$ext",$user);

    //prevent multiple uploads of the same file
    $filenames = scandir("./upload/$user");
    foreach($filenames as $fname) {
	if(strpos($fname,$fileHash)!==false)
       	    throw new RuntimeException("You've already uploaded that file.");   
    }


    if (!move_uploaded_file(
        $_FILES['upload_file']['tmp_name'],
        sprintf('./upload/%s/%s.%s',
            $user,$fileHash,$ext
        )
    )) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    //加密文件
    $baseKey = encode("$cwd/upload/$user/$fileHash.$ext",$fileHash,$user);

    //签名
    sign("$cwd/upload/$user/$fileHash.$ext",$fileHash,$user);

    //加密对称密钥
    $pub_key = openssl_pkey_get_public("file:///$cwd/sigKey/$user.crt");
    $result = openssl_public_encrypt($baseKey,$cipher_key,$pub_key);
    $cipher_key = bin2hex($cipher_key);
					
    //数据库操作
    $mysqli = new mysqli("localhost", "root", $_SERVER['MYSQL_PSW'],"login");
    if(!$mysqli)  throw new RuntimeException("数据库连接失败");
    else {
        $sql_insert = "INSERT INTO file VALUES('$fileHash','$user','$name','$ext','$url','$cipher_key')";
	//echo $sql_insert;
	$res_insert = $mysqli->query($sql_insert);
	if(!$res_insert) {
		$string = $mysqli->error;
		throw new RuntimeException("$string");
	}
    }

    //verify("$cwd/upload/$fileHash.$ext",$fileHash,$user);//验证签名

    echo "File is uploaded successfully.";

} catch (RuntimeException $e) {

    echo $e->getMessage();

}

?>
