<?php
	//error_reporting(E_ERROR | E_WARNING | E_PARSE);
	define('ABSPATH', dirname(__FILE__));

	require ABSPATH .'/lib/ez_sql_core.php';
	require ABSPATH .'/lib/ez_sql_sqlsrv.php';
	require ABSPATH .'/lib/ez_sql_mysql.php';
	require ABSPATH .'/config.php';
	require ABSPATH .'/lib/cartridge.php';
	require ABSPATH .'/lib/definitions.php';
	require ABSPATH .'/lib/loader.php';
	require ABSPATH .'/lib/ftp.class.php';
	require ABSPATH .'/lib/WideImage/WideImage.php';
	require ABSPATH .'/lib/functions.php';

	set_error_handler('error_handler');

	if(isset($_GET['action']) || isset($_POST['action'])){
		$action = isset($_GET['action']) ? $_GET['action'] : $_POST['action'];
	}else{
		$action =  '';
	}

	//print_r($selectline);

	$oxid['db'] = new ezSQL_mysql($oxid['db_user'],$oxid['db_password'],$oxid['db_name'],$oxid['db_host']);
	$selectline['db'] = new ezSQL_sqlsrv($selectline['db_user'],$selectline['db_password'],$selectline['db_name'],$selectline['db_host']);

	//$selectline['db']->get_row("SELECT * FROM ".$selectline['table_orders']."");

	if($action == 'product-import'){
		if(!oxid_userlogin()){
			echo json_encode(array('msg' => '<p class="alert alert-danger">SelectConnect konnte sich nicht bei Oxid einloggen!</p>'));
			exit();
		}
		$step = (isset($_GET['step'])) ? $_GET['step'] : 0;
		$products = new Products();
		echo json_encode(array('msg' => $products->import($step)));
		exit();
	}

	if($action == 'cron' || $argv[1] == 'cron'){
		$orders = new Orders();
		$orders->update();
		exit();
	}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="de">
<head>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SelectConnect | made by WortBildTon.de</title>

	<link href='http://fonts.googleapis.com/css?family=Roboto+Condensed:400,300,700' rel='stylesheet' type='text/css'>
	<link href='bootstrap/css/bootstrap.min.css' rel='stylesheet' type='text/css'>
	<link href='bootstrap/css/bootstrap-select.min.css' rel='stylesheet' type='text/css'>
	<link href='css/style.css' rel='stylesheet' type='text/css'>

	<script src="js/jquery-2.1.3.min.js"></script>
	<script src="js/jQuery.ajaxQueue.js"></script>
	<script src="bootstrap/js/bootstrap.min.js"></script>
	<script src="bootstrap/js/bootstrap-select.min.js"></script>

</head>
<body class="<?php echo $action; ?>">
<?php
	
	try{
		$ftp = new Ftp;
		$ftp->connect($oxid['ftp_host']);
		$ftp->login($oxid['ftp_user'],$oxid['ftp_password']);
		$ftp->close();
	}catch(Exception $e){
		print_r('<p class="alert alert-danger">SelectConnect konnte keine Verbindung zum FTP-Server herstellen!</p>');
		$action = 'error';	
	}
	
	if(!oxid_userlogin()){
		print_r('<p class="alert alert-danger">SelectConnect konnte sich nicht bei Oxid einloggen!</p>');
		$action = 'error';
	}

	switch($action):

		case 'orders':
			$orders = new Orders();
			$items = $orders->import();
			?>
			<h1>SelectConnect | Bestellungen</h1>
			<p>Es wurden <?php echo $items['items']; ?> Produkte abgeglichen und <?php echo $items['imports']; ?> importiert.</p>
			<?php 
		break;
		case 'xsel':
			$products = new Products();
			$products->set_product_xsel_oxid();
			?>
			<h1>SelectConnect | Crosselling</h1>
			<p>Das Crossseling wurde importiert.</p>
			<?php
		break;
		case 'products':
			$maxImports = Products::count_products_from_selectline(); 
			$step = (isset($_GET['step'])) ? $_GET['step'] : 0;
			$limit = $selectline['limit'];
		?>
		<h1>SelectConnect | made by WortBildTon.de</h1>
		<div class="product">
			<div class="loading">
				<div class="sk-spinner sk-spinner-double-bounce">
			      <div class="sk-double-bounce1"></div>
			      <div class="sk-double-bounce2"></div>
			    </div>
		    	<div class="result">
		    		<p>SelectConnect gleicht die Produkte mit dem Onlineshop und der Warenwirstschaft ab, bitte warten.</p>
		    		<p>Produkt <span class="counter"><?php echo $step*$limit; ?></span> von <?php echo $maxImports; ?> wird überprüft und ggf. importiert</p>
		    	</div>
		    	<p><a href="/selectline" title="Zurück">> Zurück</a></p>
		    	<script type="text/javascript">
				jQuery(document).ready(function($){
					
					for(var i = 0; i <= Math.floor(<?php echo ($maxImports/$limit); ?>);i++){
						$.ajaxQueue({
							dataType: 'json',
							url: '?action=product-import&overwrite=<?php echo $_GET['overwrite']; ?>&images=<?php echo $_GET['images']; ?>&xsel=<?php echo $_GET['xsel']; ?>&step='+i,
						}).done(function(data){
							if(parseInt(data.msg) > 0){
								$('.product .counter').html(data.msg);
							}
						});
					}
					
				});
				</script>
			</div>
		</div>

		<?php
			
		break;

		

		case 'categories':
			?>
			<h1>SelectConnect | Kategorien</h1>
			<?php
			$categories = new Categories();
			echo $categories->import();
		break;

		case 'settings':

		break;
		
		case 'error':

		break;
		
		default:
			?>
			<h1>SelectConnect | made by WortBildTon.de</h1>
			<p>
				<select data-style="btn-primary" class="main-nav">
					<option value="-1">Bitte Aktion wählen</option>
					<option value="products">Produkte</option>
					<option value="category">Kategorien</option>
					<option value="xsel">XSel-Produkt</option>
					<option value="orders">Bestellungen</option>
				</select>
			</p>

			<div class="panel products hide">
				<form action="" method="get"/>
					<div class="form-group">
						<label>Sollen Produkte überschrieben werden?</label>
						<select class="form-control" name="overwrite">
							<option value="0">Nein</option>
							<option value="1">Ja</option>
						</select>
					</div>
					<div class="form-group">
						<label>Möchten Sie die Bilder importieren?</label>
						<select class="form-control" name="images">
							<option value="0">Nein</option>
							<option value="1">Ja</option>
						</select>
					</div>
					<?php /*<div class="form-group">
						<label>Soll das Crossselling übernommen werden?</label>
						<select class="form-control" name="xsel">
							<option value="1">Ja</option>
							<option value="0">Nein</option>
						</select>
					</div>*/ ?>
					<input type="hidden" name="action" value="products" />
					<p><button class="btn btn-success" type="submit">Prozess starten</button></p>
				</form>
			</div>
			<div class="panel xsel hide">
				<form action="" method="get"/>
					<input type="hidden" name="action" value="xsel" />
					<p><button class="btn btn-success" type="submit">Prozess starten</button></p>
				</form>
			</div>
			<div class="panel orders hide">
				<form action="" method="get"/>
					<input type="hidden" name="action" value="orders" />
					<p><button class="btn btn-success" type="submit">Prozess starten</button></p>
				</form>
			</div>
			<?php /*<p>
				<a href="?action=categories" title="Kategorien aus SelectLine exportieren">» Kategorien aus SelectLine exportieren</a><br />
				<a href="?action=products" title="Produkte aus SelectLine exportieren">» Produkte aus SelectLine exportieren</a><br />
				<a href="?action=xsel" title="XSel-Produkte aus SelectLine exportieren">» XSel-Produkte aus SelectLine exportieren</a><br />
				<a href="?action=orders" title="Bestellungen aus OXID exportieren">» Bestellungen aus OXID exportieren</a><br />
			</p>*/ ?>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					$('select').selectpicker();

					$('select.main-nav').change(function(){
						$('.panel').addClass('hide');
						$('.'+$(this).val()).removeClass('hide');
					});
				});
			</script>
			<?php
		break;

	endswitch;
?>
</body>
</html>