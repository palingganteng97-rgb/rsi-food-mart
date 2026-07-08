<?php
header("Access-Control-Allow-Origin: *");header("Access-Control-Allow-Headers: *");header("Access-Control-Allow-Methods: *");header("Content-Type: application/json");
$file='db.json';$method=$_SERVER['REQUEST_METHOD'];
$db=file_exists($file)?json_decode(file_get_contents($file),true):["produk"=>[]];
if(!$db)$db=["produk"=>[]];
if(!isset($db['pesanan']))$db['pesanan']=[];
if($method!=='POST'){echo json_encode(["status"=>"method tidak didukung"]);exit;}
$input=json_decode(file_get_contents('php://input'),true);
if(!is_array($input))$input=[];
$input['id']=strval(time());
$input['waktu_pesan']=$input['waktu_pesan']??date('c');
$input['total_harga']=$input['total_harga']??0;
$input['daftar_item']=$input['daftar_item']??[];
$db['pesanan'][]=$input;
file_put_contents($file,json_encode($db,JSON_PRETTY_PRINT));
echo json_encode(["status"=>"sukses","pesanan"=>$input]);
?>
