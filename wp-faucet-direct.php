<?php
/**
 * Plugin Name: WP Faucet Direct
 * Plugin URI: https://seoland.es/plugin-faucet-direct
 * Description: With WP Faucet Direct you can create your direct payment faucet in a simple way in your WordPress page by simply adding a shortcode in the section of the page where you want it to appear
 * Version: 1.4
 * Author: Jose Sinisterra
 * Author URI: https://www.facebook.com/jose0912
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: faucet_blockio
 */
 
define("wpfblockio_TBL_SOLICITUDES","blockio_solicitudes");
define("wpfblockio_TBL_AJUSTES","blockio_ajustes");

require_once 'lib/block_io.php';
require_once 'lib/solvemedialib.php';

function wpfblockio_get_option($nombre, $tipo='')
{
	global $wpdb;
	
	$tablename = $wpdb->prefix.wpfblockio_TBL_AJUSTES;
	$sql_prepare = $wpdb->prepare("SELECT * FROM ".$tablename." WHERE nombre='%s'", $nombre);	
	$sql_get = $wpdb->get_results($sql_prepare, 'ARRAY_A');
	
	$option = '';
	
	if(isset($sql_get[0]))
	{
		$option = $sql_get[0];	
	}
	
	if((isset($option['nombre']) && $option['nombre']!="") || $tipo=='')
	{
		$valor = '';
		if(isset($option['valor']))
		{
			$valor = $option['valor'];
		}
		return $valor;			
	}
	else
		return 'noexiste';
}

function wpfblockio_set_option($nombre, $valor)
{
	global $wpdb;
	
	$nombre = sanitize_text_field($nombre);
	$valor = sanitize_text_field($valor);
	
	if(wpfblockio_get_option($nombre, 'verifica')=="noexiste")
	{			
		$wpdb->insert($wpdb->prefix.wpfblockio_TBL_AJUSTES,array('nombre' => $nombre, 'valor'=> $valor, 'date_update'=>date('Y-m-d H:i:s')),array('%s','%s','%s'));	
	}
	else
	{
		$wpdb->update($wpdb->prefix.wpfblockio_TBL_AJUSTES,
			array('valor' => $valor), 
			array('nombre' => $nombre), 
			array('%s'), 
			array('%s') 
		);			
	}
}

function wpfblockio_exec_api_blockio($tipo, $wallet='', $amount='', $moneda_actual='')
{
	$apiKey = wpfblockio_get_option($moneda_actual.'_apikey');
	$version = 2; // API version	
	$pin = wpfblockio_get_option($moneda_actual.'_pin');
	$block_io = new wpfblockio_BlockIo($apiKey, $pin, $version);
	
	if($tipo=='balance')
	{
		$fecha = date('Y-m-d');
		$fecha_balance = wpfblockio_get_option($moneda_actual.'_fecha_balance');
		
		$balance = wpfblockio_get_option($moneda_actual.'_balance_actual');
		
		if($fecha!=$fecha_balance)
		{		
			try {
				$balance = $block_io->get_balance();
				$balance = $balance->data->available_balance;
			} catch (Exception $e) {
				if($_GET['show_errors']==1)
				{
					echo '<br>ERROR 001: ',  $e->getMessage(), "\n";
				}
			}
			
			wpfblockio_set_option($moneda_actual.'_balance_actual', $balance);
		}
		
		return $balance;
	}
}

function wpfblockio_envia_correo($destino,$asunto,$contenido,$remite='')
{
	if($remite=='')
	{
		$remite = get_bloginfo('admin_email');
	}
	
	$headers = "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	$headers .= "From: ".get_bloginfo('blogname')." <".$remite."> \r\n";	

	$asunto = utf8_decode($asunto);
	$contenido = utf8_decode($contenido);
	
	if(mail($destino, $asunto, $contenido, $headers))					
		return true;
	else
		return false;	
}

function wpfblockio_verifica_wallet($wallet)
{
	if (extension_loaded('gmp')) {
		$block_io = new wpfblockio_BlockKey();
		return $block_io->validateAddress($wallet);	
	}
	else
	{
		return true;	
	}
}

function wpfblockio_load_db() {
  global $wpdb;
 
  $sql_create1="
  	CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." (
      id_solicitud bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	  faucet varchar(20) NOT NULL DEFAULT '',
      wallet varchar(320) NOT NULL DEFAULT '',
      cantidad float NOT NULL,
	  pagado int(2) NOT NULL,
	  ip varchar(100) NOT NULL,
	  registro datetime NOT NULL,
      PRIMARY KEY (id_solicitud)
    );";
	
  $sql_create2="
	CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.wpfblockio_TBL_AJUSTES." (
      id_ajuste bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	  nombre varchar(100) NOT NULL DEFAULT '',
      valor text NOT NULL,	  
	  date_update datetime NOT NULL,
      PRIMARY KEY (id_ajuste)
    );";
	
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql_create1);
  dbDelta($sql_create2);  
} 

function wpfblockio_init(){	
	$url_lang = dirname( plugin_basename(__FILE__) ) ."/languages";	
	load_plugin_textdomain('faucet_blockio', false, $url_lang);
}
add_action('init','wpfblockio_init');

function wpfblockio_menu() {
	
	$coins = explode(',',wpfblockio_get_option('coins'));
	
	add_menu_page('Faucet Direct', 'Faucet Direct', 'administrator', 'wpfblockio_settings', 'wpfblockio_settings_page', 'dashicons-admin-generic');
	
	$prefix = '';
	
	add_submenu_page('wpfblockio_settings', __( 'Requests', 'faucet_blockio' ), __( 'Requests', 'faucet_blockio' ),'manage_options', 'wpfblockio_solicitudes_'.$prefix, 'wpfblockio_solicitudes_page');
	
	
	add_submenu_page('wpfblockio_settings', __( 'Accumulated', 'faucet_blockio' ), __( 'Accumulated', 'faucet_blockio' ),'manage_options', 'wpfblockio_acumulado_'.$prefix, 'wpfblockio_acumulado_page');
		
}
add_action( 'admin_init', 'wpfblockio_settings' );

function wpfblockio_settings() {
	
}

function wpfblockio_settings_page()
{	
	$moneda_actual = '';
	
	$parametros = array("_apikey","_pin","_name_coin","_limite","_time","_email","_name_btn","_prob1","_prob1_p","_prob2","_prob2_p","_prob3","_prob3_p","_solvemedia_challenge_key","_solvemedia_private_key","_solvemedia_hash_key");	
	
	if(isset($_POST['set_ajustes']) && $_POST['set_ajustes']!="")
	{
		foreach($parametros as $var)
		{
			$nombre = $moneda_actual.$var;
			$valor = '';
			if(isset($_POST[$nombre]))
			{
				$valor = sanitize_text_field($_POST[$nombre]);
			}
			
			wpfblockio_set_option($nombre, $valor);			
		}
	}
	
	$shorcode = '[faucet_blockio]';
	if($moneda_actual!="")
	{
		$shorcode = "[faucet_blockio faucet='".$moneda_actual."']";		
	}
?>
<div class="wrap content_faucet_blockio">
<h2><?php _e( 'Settings', 'faucet_blockio' ) ?> Faucet Direct</h2>

<?php wpfblockio_show_premium();?>

<div class="shorcode">
	<strong>SHORTCODE:</strong> <?php echo $shorcode;?>
</div>

<form method="post">
    <table class="form-table" style="width:700px">
        <tr valign="top">
        	<th colspan="2"><h3>Type your <a href="https://goo.gl/vZE2wC" target="_blank">block.io</a> credentials  or create an account</h3></th>
        </tr>
        <tr valign="top">
        <th scope="row">apiKey: </th>
        <td>
        	<input type="text" name="_apikey" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_apikey'));?>" placeholder="XXXX-XXXX-XXXX-XXXX">
        </td>
        </tr>
        <tr valign="top">
        <th scope="row">PIN: </th>
        <td>
        	<input type="password" name="_pin" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_pin'));?>">
            <br><br>
            <a href="https://goo.gl/3SxMJa" target="_blank">How to do this?</a>
        </td>
        </tr>
        <tr valign="top">
        <td colspan="2">--------------------------------------------------------------------------------------------------</td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php _e( 'Currency Name', 'faucet_blockio' ) ?>: </th>
        <td>
        	<input type="text" name="_name_coin" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_name_coin'));?>">
        </td>
        </tr>        
        <tr valign="top">
        <th scope="row"><?php _e( 'Limit to send payment', 'faucet_blockio' ) ?>: </th>
        <td>
        	<input type="text" name="_limite" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_limite'));?>">
        </td>
        </tr>                  
        <tr valign="top">
        <th scope="row"><?php _e( 'Wait time', 'faucet_blockio' ) ?>: </th>
        <td>
        	<input type="text" name="_time" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_time'));?>"> <?php _e( 'minutes', 'faucet_blockio' ) ?>
        </td>
        </tr>
        <tr valign="top">
        <td colspan="2">--------------------------------------------------------------------------------------------------</td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php _e( 'Email for notifications', 'faucet_blockio' ) ?>: </th>
        <td>
        	<input type="text" name="_email" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_email'));?>">
        </td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php _e( 'Reward button value', 'faucet_blockio' ) ?>: </th>
        <td>
        	<input type="text" name="_name_btn" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_name_btn'));?>">
        </td>
        </tr>        
        <tr>
        <th colspan="2">
        	<h3><?php _e( 'Probabilities', 'faucet_blockio' ) ?></h3>
       	</th>
        </tr>
        <tr valign="top">
        <th>#1 <input type="text" name="_prob1" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_prob1'));?>" placeholder="<?php _e( 'Amount', 'faucet_blockio' ) ?>"></th>
        <td>
        	<input type="text" name="_prob1_p" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_prob1_p'));?>" placeholder="<?php _e( 'Percentage', 'faucet_blockio' ) ?>">%
        </td>
        <tr valign="top">
        <th>#2 <input type="text" name="_prob2" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_prob2'));?>" placeholder="<?php _e( 'Amount', 'faucet_blockio' ) ?>"></th>
        <td>
        	<input type="text" name="_prob2_p" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_prob2_p'));?>" placeholder="<?php _e( 'Percentage', 'faucet_blockio' ) ?>">%
        </td>
        <tr valign="top">
        <th>#3 <input type="text" name="_prob3" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_prob3'));?>" placeholder="<?php _e( 'Amount', 'faucet_blockio' ) ?>"></th>
        <td>
        	<input type="text" name="_prob3_p" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_prob3_p'));?>" placeholder="<?php _e( 'Percentage', 'faucet_blockio' ) ?>">%
        </td>
        </tr>
        <tr>
        	<th colspan="2"><small>* <?php _e( 'The sum of the percentages being 100%', 'faucet_blockio' ) ?></small></th>
        </tr> 
        <tr valign="top">
        <td colspan="2">--------------------------------------------------------------------------------------------------</td>
        </tr>             
        <tr>
        <th colspan="2">
        	<h3><?php _e( 'CAPTCHA Solvemedia', 'faucet_blockio' ) ?></h3>
       	</th>
        </tr>
        <tr valign="top">
        <th scope="row">Challenge Key (C-key) (Public): </th>
        <td>
        	<input type="text" name="_solvemedia_challenge_key" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_solvemedia_challenge_key'));?>">
        </td>
        </tr>
        <tr valign="top">
        <th scope="row">Verification Key (V-key) (Private): </th>
        <td>
        	<input type="text" name="_solvemedia_private_key" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_solvemedia_private_key'));?>">
        </td>
        </tr>
        <tr valign="top">
        <th scope="row">Authentication Hash Key (H-key): </th>
        <td>
        	<input type="text" name="_solvemedia_hash_key" value="<?php echo esc_html(wpfblockio_get_option($moneda_actual.'_solvemedia_hash_key'));?>">
        </td>
        </tr>
        <tr valign="top">
        <td colspan="2">--------------------------------------------------------------------------------------------------</td>
        </tr>            
    </table>   
    <input type="hidden" name="set_ajustes" value="1" /> 
    <?php submit_button(); ?>
</form>
</div>
<?php	
}

function wpfblockio_solicitudes_page() {	
	global $wpdb;	
?>
    <div class="wrap content_faucet_blockio">
    <h2><?php _e( 'Requests', 'faucet_blockio' ) ?> Faucet Direct</h2>
    
    <table class="form-table border">
    	<thead>
        	<tr>
                <th style="width:30px">ID</th>
                <th><?php _e( 'Wallet', 'faucet_blockio' ) ?></th>
                <th><?php _e( 'Amount', 'faucet_blockio' ) ?></th>
                <th><?php _e( 'IP', 'faucet_blockio' ) ?></th>
                <th><?php _e( 'Date', 'faucet_blockio' ) ?></th>
          	</tr>
        </thead>
        <tbody>
<?php
	$sql_prepare = $wpdb->prepare("SELECT * FROM ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." ORDER BY id_solicitud DESC LIMIT 0, %d", 100);	
	$questions = $wpdb->get_results($sql_prepare, 'ARRAY_A');
	
	foreach ($questions as $question) 
	{
	?>
    		<tr>
				<td style="width:30px"><?php echo esc_html($question['id_solicitud']);?></td>
				<td><?php echo esc_html($question['wallet']);?></td>
                <td><?php echo esc_html($question['cantidad']);?></td>
				<td><?php echo esc_html($question['ip']);?></td>
                <td><?php echo esc_html($question['registro']);?></td>			
			</tr>
 	<?php
	}
?>
		</tbody>
	</table>
	</div>
<?php
}

function wpfblockio_show_premium()
{
?>
	<div class="show_premium">
    <h3><a href="https://goo.gl/LWhmvd" target="_blank"><?php _e( 'Upgrade your WP Faucet Direct to PRO plugin', 'faucet_blockio' ) ?></a></h3>
	<h4><?php _e( 'Features', 'faucet_blockio' ) ?>:</h4>
	<ul>
    	<li>
			<?php _e( 'Multifaucet, you can offer 3 different currencies in each section of your faucet or page', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( 'Direct payments to the wallets of the users of the faucet', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( 'Instant direct payments or by accumulation of the amount that you establish', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( 'Payments can be set from your panel manually or automatically', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( 'Check the wallet addresses that make requests in your faucet', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( 'Check the IPs that make requests in your faucet', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( "Consult the total accumulated for each wallet, the number of times that I request coins and since IP or IP's", 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( 'Filters: You can easily consult the address of a wallet, or the IP, everything related to requests, accumulated IPs of requests, hours of complaint, etc.', 'faucet_blockio' ) ?>
       	</li>
        <li>
			<?php _e( ' You can easily delete a wallet or a certain request for coins	', 'faucet_blockio' ) ?>
       	</li>
    </ul>
    <a class="button button-primary" href="https://goo.gl/LWhmvd" target="_blank"><?php _e( 'Upgrade your WP Faucet Direct to PRO plugin', 'faucet_blockio' ) ?></a>
    </div>
<?php		
}

function wpfblockio_acumulado_page() {	
	global $wpdb;	
	$moneda_actual = '';
	
	$limite = floatval(wpfblockio_get_option($moneda_actual.'_limite'));
	$moneda = wpfblockio_get_option($moneda_actual.'_name_coin');	
?>
    <div class="wrap content_faucet_blockio border">  
    <h2><?php _e( 'Accumulated', 'faucet_blockio' ) ?> Faucet Direct</h2>
    
    <?php 
	
	if(isset($_POST['show_premium']) && $_POST['show_premium']!='')
	{
		wpfblockio_show_premium();	
	}
	
	?>		
	<table class="form-table border">
		<thead>
			<tr>
				<th>#</th>
				<th><?php _e( 'Wallet', 'faucet_blockio' ) ?></th>
				<th><?php _e( 'Accumulated', 'faucet_blockio' ) ?></th>
				<th># <?php _e( 'Requests', 'faucet_blockio' ) ?></th>
				<th><?php _e( 'IP', 'faucet_blockio' ) ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
	<?php	
		$sql_prepare = $wpdb->prepare("SELECT ROUND(SUM(cantidad),8) as acumulado, wallet FROM ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." WHERE faucet='%s' AND pagado=0 GROUP BY wallet ORDER BY acumulado DESC", $moneda_actual);
		$wallets = $wpdb->get_results($sql_prepare, 'ARRAY_A');
		
		$x=1;
		foreach ($wallets as $row_wallet) 
		{
			$wallet_busq = sanitize_text_field($row_wallet['wallet']);
			$sql_prepare2 = $wpdb->prepare("SELECT * FROM ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." WHERE pagado=0 AND wallet='%s'", $wallet_busq);
			
			$solicitudes = $wpdb->get_results($sql_prepare2, 'ARRAY_A');
			
			$btn_pago = '';	
			$acumulado_total = 0;
			$ips = array();
			
			$y=0;
			
			foreach ($solicitudes as $solicitud) 
			{
				$acumulado_total+=floatval($solicitud['cantidad']);
				
				if(!in_array($solicitud['ip'],$ips))
				{
					array_push($ips,$solicitud['ip']);		
				}
				$y++;
			}
			
			if($acumulado_total>=$limite)
			{
				$btn_pago = '
				<form method="post">
					<input type="hidden" name="show_premium" value="1" />
					<input type="submit" class="button button-primary" value="'.__( 'Pay', 'faucet_blockio').'" />
				</form><br>';
			}
			
			?>
			<tr>
				<td><?php echo esc_html($x);?></td>
				<td><?php echo esc_html($row_wallet['wallet']);?></td>
				<td><?php echo esc_html($acumulado_total.' '.$moneda.'(s)');?></td> 
				<td><?php echo esc_html($y);?></td>
				<td><?php echo esc_html(implode(', ',$ips));?></td>               
				<td>
					<?php echo $btn_pago;?>
                </td>		
			</tr>
			<?php
			
			$x++;
		}
	?>
		</tbody>
	</table>
	</div>
<?php
}

function wpfblockio_load_css() {
	wp_register_style( 'wpfblockio_style', plugins_url( 'css/style.css', __FILE__ ) );
	wp_enqueue_style( 'wpfblockio_style' );
}
function wpfblockio_admin_enqueue($hook) {
	wp_enqueue_style ( 'wpfblockio_admin_style', plugins_url('css/admin-style.css', __FILE__) );
}

register_activation_hook( __FILE__, 'wpfblockio_load_db' );

add_action('admin_menu', 'wpfblockio_menu');
add_action('admin_enqueue_scripts', 'wpfblockio_admin_enqueue' );

add_action('wp_print_styles', 'wpfblockio_load_css');

function wpfblockio_get_user_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function wpfblockio_balance_wallet($wallet, $moneda_actual='')
{
	global $wpdb;	
	
	$wallet = sanitize_text_field($wallet);	
	
	$sql_prepare = $wpdb->prepare("SELECT ROUND(SUM(cantidad),8) as balance FROM ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." WHERE wallet='%s' AND pagado=0", $wallet);	
	
	$balance = $wpdb->get_row($sql_prepare,'ARRAY_A');	
	return $balance['balance'];
}


function wpfblockio_shortcode ($atts = array(), $content = null, $code = "" ) {	
	global $wpdb;
	
	$moneda_actual=$error=$html=$success='';
	
	$existe_coin = true;
	
	// COMPROBAR QUE EL FAUCET ESTE CREADO
	if($moneda_actual!="")
	{
		$coins = explode(',',wpfblockio_get_option('coins'));	
		
		if(!in_array($moneda_actual, $coins))
		{
			$existe_coin = false;			
		}	
	}
	
	if($existe_coin)
	{		
		$apikey = wpfblockio_get_option($moneda_actual.'_apikey');
		$pin = wpfblockio_get_option($moneda_actual.'_pin');
		
		// COMPROBAR QUE EXISTAN LOS DATOS DE BLOCKIO
		if($apikey!="" && $pin!="")
		{			
			$pago1 = wpfblockio_get_option($moneda_actual.'_prob1');
			$pago2 = wpfblockio_get_option($moneda_actual.'_prob2');
			$pago3 = wpfblockio_get_option($moneda_actual.'_prob3');
			
			$porc1 = intval(wpfblockio_get_option($moneda_actual.'_prob1_p'));
			$porc2 = intval(wpfblockio_get_option($moneda_actual.'_prob2_p'));
			$porc3 = intval(wpfblockio_get_option($moneda_actual.'_prob3_p'));
			
			$moneda = wpfblockio_get_option($moneda_actual.'_name_coin');
			
			$solvemedia_challenge_key = wpfblockio_get_option($moneda_actual.'_solvemedia_challenge_key');
			$solvemedia_private_key = wpfblockio_get_option($moneda_actual.'_solvemedia_private_key');
			$solvemedia_hash_key = wpfblockio_get_option($moneda_actual.'_solvemedia_hash_key');
		
			$value_btn = wpfblockio_get_option($moneda_actual.'_name_btn');
			if($value_btn=="")
			{
				$value_btn = __('Get reward', 'faucet_blockio');
			}
		
		
			// CONSULTAR CODIGO ADS	
			$html_ads = wpfblockio_get_option($moneda_actual.'_ads');
		
			if($html_ads!="")
			{
				$html_ads = stripslashes($html_ads);			
			}
		
			// VERIFICAR HACE CUANTO RECLAMOS LA MISMA IP
			
			$sql_prepare = $wpdb->prepare("SELECT * FROM ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." WHERE ip='%s' ORDER BY id_solicitud DESC", wpfblockio_get_user_ip());

			$sql = "";	
			$solicitud = $wpdb->get_row($sql_prepare,'ARRAY_A');
			
			$last_date = strtotime(date($solicitud['registro']));	
			$now_date = strtotime(date('Y-m-d H:i:s'));	
			
			$diferencia = $now_date-$last_date;
			
			$seg_esperar = wpfblockio_get_option($moneda_actual.'_time')*60;
			
			if($seg_esperar<$diferencia)
			{	
				if(isset($_POST['solicitud_coins']) && $_POST['solicitud_coins']!="" && isset($_POST['moneda_actual']) && $_POST['moneda_actual']==$moneda_actual)
				{
					$wallet = sanitize_text_field($_POST['wallet_solicitud']);
					
					if(wpfblockio_verifica_wallet($wallet))
					{
						
						//VERIFICAR TOKEN		
						$token2 = '"'.intval((((((intval($_POST['token'])*85)/109)+2)*2)/5)).'"';
						$token2_p = '"'.intval($_POST['token2']).'"';
						
						// CONDICION CUANDO EXITEN DATOS DE SOLVEMEDIA
						if($solvemedia_private_key!="")
						{
							$exito_solve = false;
							if(isset($_POST['adcopy_challenge']) && isset($_POST['adcopy_response']))
							{
								
								$solvemedia_response = wpfblockio_solvemedia_check_answer($solvemedia_private_key,
									$_SERVER["REMOTE_ADDR"],
									sanitize_text_field($_POST["adcopy_challenge"]),
									sanitize_text_field($_POST["adcopy_response"]),		
									$solvemedia_hash_key
								);
								
								
								if (!$solvemedia_response->is_valid) {
									$error = "Error: ".$solvemedia_response->error;
								}
								else {
									$exito_solve = true;
								}
							}
							else
							{
								$error = 'ERROR: '.__('Solvemedia, Invalid parameters', 'faucet_blockio');
							}
						}
						else
						{
							$exito_solve = true;		
						}
						
						if($exito_solve)
						{
							if($token2==$token2_p)
							{
								if($wallet!="")
								{
									$sql_prepare2 = $wpdb->prepare("SELECT * FROM ".$wpdb->prefix.wpfblockio_TBL_SOLICITUDES." WHERE wallet='%s' ORDER BY id_solicitud DESC", $wallet);

									$solicitud = $wpdb->get_row($sql_prepare2,'ARRAY_A');
									
									$last_date = strtotime(date($solicitud['registro']));	
									$now_date = strtotime(date('Y-m-d H:i:s'));	
									
									$diferencia = $now_date-$last_date;
									
									$seg_esperar = wpfblockio_get_option($moneda_actual.'_time')*60;
									
									$cantidad_limite = intval(wpfblockio_get_option($moneda_actual.'_limite'));
									
									$num_ale = rand(1, 100);
									
									$var_ini = 1;
									$var_ini_limite = $porc1;
									
									$var_ini2 = $var_ini_limite;
									$var_ini2_limite = $var_ini2+$porc2;
									
									$var_ini3 = $var_ini2_limite;
														
									//OBTENER LA PROBABILIDAD 1
									if($num_ale>=1 && $num_ale<=$porc1)
									{
										$cantidad_abonar = $pago1;
									}
									elseif($num_ale>$var_ini2 && $num_ale<=$var_ini2_limite)
									{
										$cantidad_abonar = $pago2;		
									}
									else
									{
										$cantidad_abonar = $pago3;				
									}
																						
									if($seg_esperar < $diferencia)
									{
										if($cantidad_abonar==0)
										{
											$error = 'ERROR: '.__('Rewards need to be set up', 'faucet_blockio');	
										}
										else
										{
											$wpdb->insert(
												$wpdb->prefix.wpfblockio_TBL_SOLICITUDES,
												array(
													'wallet' => $wallet,
													'faucet' => $moneda_actual,
													'cantidad'=> $cantidad_abonar,
													'ip'=>wpfblockio_get_user_ip(),
													'registro'=>date('Y-m-d H:i:s')
												),
												array('%s','%s','%s','%s','%s')
											);		
											
											if($wpdb->insert_id)
											{
												$balance_wallet = wpfblockio_balance_wallet($wallet, $moneda_actual);
												$success = __('Reward:', 'faucet_blockio').' '.$cantidad_abonar.' '.$moneda.'<br>'.__('Successful process. Its balance is:', 'faucet_blockio').' '.$balance_wallet.' '.$moneda;
												
												if($cantidad_limite<=$balance_wallet)
												{
													$destino = wpfblockio_get_option($moneda_actual.'_email');
													if($destino!="")
													{
													$html_email = '<h2>'.__('A new wallet has reached the limit for payment', 'faucet_blockio').'</h2><br><strong>'.__('Wallet', 'faucet_blockio').'</strong>: '.$wallet.'<br><strong>'.$moneda.' '.__('accumulated', 'faucet_blockio').'</strong>: '.$balance_wallet;
													wpfblockio_envia_correo($destino,__('A new wallet has reached the limit for payment', 'faucet_blockio'),$html_email);
													}
													
												}
											}
											else
											{
												$error = 'ERROR 010: '.__('Contact the administrator', 'faucet_blockio');			
											}
										}
									}
									else
									{
										$min_faltantes = round(($seg_esperar-$diferencia)/60);
										$error = __( 'Must wait', 'faucet_blockio' ).' '.$min_faltantes.' '.__( 'minutes', 'faucet_blockio' );	
									}
								}
								else
								{
									$error = __('You must enter a wallet address', 'faucet_blockio');	
								}	
							}
							else
							{
								$error = __('Invalid process1', 'faucet_blockio');	
							}	
						}
						
						$class = 'success';
						if($error!="")
						{
							$class = 'error';	
						}
						
						$html='<div class="'.$class.'">'.$error.$success.'</div>';	
					}
					else
					{
						$html='<div class="error">'.__('Invalid wallet address', 'faucet_blockio').'</div>';			
					}
				}
				else
				{
					$token = rand(0,1000);
					$token2 = (((($token*85)/109)+2)*2)/5;
					
					$html_solvemedia = '';
					
					if($solvemedia_challenge_key!="")
					{
						$html_solvemedia = '<center>'.wpfblockio_solvemedia_get_html($solvemedia_challenge_key, null, true).'</center><br>';
					}
					
					$html = '
					<form method="post">
						<input type="text" name="wallet_solicitud" placeholder="Ej: DDRBr6VL7oWjhp3JCGmnz24eLwMARBD8MW" required>
						<input type="hidden" name="moneda_actual" value="'.$moneda_actual.'">
						<input type="hidden" name="solicitud_coins" value="1">
						<input type="hidden" name="token" value="'.$token.'">
						<input type="hidden" name="token2" value="'.$token2.'">
						'.$html_solvemedia.'
						<div class="content_ads">
							'.$html_ads.'
						</div>
						<input type="submit" class="btn" value="'.$value_btn.'">
					</form>
					';	
				}
			}
			else
			{
				$min_faltantes = round(($seg_esperar-$diferencia)/60);
				$error = __( 'Must wait', 'faucet_blockio' ).' '.$min_faltantes.' '.__( 'minutes', 'faucet_blockio' );	
				$html='<div class="error">'.$error.'</div><br><br>';				
			}
			
			
			$balance_general = wpfblockio_exec_api_blockio('balance', '', '', $moneda_actual);
			$balance_entero = intval($balance_general);
			
			if($balance_general>0 && $balance_entero>0)
			{		
				$divi = $balance_general/$balance_entero;
				
				if($divi==1)
				{
					$balance_general = $balance_entero;
				}
				else
				{
					$balance_general = round(wpfblockio_exec_api_blockio('balance','','',$moneda_actual),4);		
				}
			}
			
			$html_pagos = '';
			
			if($pago1!="")
			{
				$html_pagos.= $pago1.' ('.$porc1.'%)';		
			}
			if($pago2!="")
			{
				$html_pagos.= ', '.$pago2.' ('.$porc2.'%)';		
			}
			if($pago3!="")
			{
				$html_pagos.= ', '.$pago3.' ('.$porc3.'%)';		
			}
			if($moneda!="")
			{
				$html_pagos.= ' '.$moneda.'(s)';		
			}
			
			
			$html = '<h3>'.__('Balance Faucet', 'faucet_blockio').': '.$balance_general.' '.wpfblockio_get_option($moneda_actual.'_name_coin').'</h3>
						'.__('Rewards', 'faucet_blockio').' '.$html_pagos.'<br><br>'.$html;	
		}
		else
		{
			$html = '<div class="error"> ERROR 019: '.__('You must configure the credentials of ', 'faucet_blockio').' Block.io</div>';			
		}
	}
	else
	{
		$html = '<div class="error"> ERROR 020: '.__('Faucet does not exist', 'faucet_blockio').'</div>';		
	}
	
	return '<div id="wp_faucet">'.$html.'</div>';
}
add_shortcode('faucet_blockio', 'wpfblockio_shortcode');


function wpfblockio_add_btns( $links ) {

	return array_merge(
		array(
			'settings' => '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wpfblockio_settings">' . __( 'Settings', 'faucet_blockio' ) . '</a>',
			'premium' => '<a href="https://goo.gl/LWhmvd" target="_blank">' . __( 'Get Premium Version', 'faucet_blockio' ) . '</a>'
		),
		$links
	);
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpfblockio_add_btns' );

add_filter('widget_text','do_shortcode');

?>