<?php

$hotmart = new HotmartTransaction();
$path = $hotmart->dirname_for(4);
require_once($path . '/wp-load.php');
$carpeta = dirname(dirname(__FILE__));
require_once($carpeta . '/miembro-press.php');

class HotmartTransaction{
    public $levelsTable;
    public $hotmartTable;
    public $transaction;
    public $prod;
    public $prod_name;
    public $off;
    public $email;
    public $name;
    public $doc;
    public $name_subscription_plan;
    public $recurrency_period;
    public $subscription_status;
    public $hash_level;
    public $status;
    public $purchase_date;
    public $subscriber_code;

    function __construct(){
        global $miembropress;
        $this->hotmartTable = "";
        $this->levelsTable = "";
        $this->transaction = "";
        $this->prod = "";
        $this->prod_name = "";
        $this->off = NULL;
        $this->email = NULL;
        $this->name = NULL;
        $this->doc = NULL;
        $this->name_subscription_plan = NULL;
        $this->recurrency_period = NULL;
        $this->subscription_status = NULL;
        $this->hash_level = "";
        $this->status = "";
        $this->purchase_date = date('d-m-Y H:i:s');
        $this->subscriber_code = NULL;
    }

	function dirname_for($cantidad){
		$url = dirname(__FILE__);
		for($i = 0; $i<$cantidad; $i++){
			$url = dirname($url);
		}
		return $url;
	}

    function consulta_items($producto){
        global $miembropress;
        $hash_level = "";
        $levels = $miembropress->model->getLevels();
        $items = $miembropress->model->setting("hotmart_items");
        foreach ($levels as $level){
            $item = "";
            if (isset($items[$level->ID])) {
                $item = $items[$level->ID];
                if($item == $producto){
                    $hash_level = $level->ID;
                }
            }
        }
        return $hash_level;
    }
    
    function obtener_hash($hash_level){
        global $wpdb;
        $hashlink = $wpdb->get_var( "SELECT level_hash FROM {$this->levelsTable} WHERE ID = '$hash_level'" );
        return $hashlink;
    }
    
    function obtener_hash_por_offert($name_subscription_plan){
        global $wpdb;
        $hashlink = $wpdb->get_var( "SELECT level_hash FROM {$this->levelsTable} WHERE level_name = '$name_subscription_plan'" );
        return $hashlink;
    }
    
    function generar_enlace($hashlink){
        global $miembropress;
        $checkout = $miembropress->model->signupURL($hashlink);
        return htmlentities($checkout);
    }

    function save_transaction(){
        global $wpdb;
        $data = array(
			'ID' => null,
			'transaction' => $this->transaction, 
			'prod' => $this->prod, 
            'prod_name' => $this->prod_name,
            'purchase_date' => $this->purchase_date,
			'off' => $this->off,
			'email' => $this->email,
			'name' => $this->name,
            'doc' => $this->doc,
            'subscriber_code' => $this->subscriber_code,
			'name_subscription_plan' => $this->name_subscription_plan,
			'recurrency_period' => $this->recurrency_period,
			'subscription_status' => $this->subscription_status,
			'hash_level' => $this->hash_level,
			'status' => $this->status
		);
		$format = array('%s','%s');
		$wpdb->insert($this->hotmartTable, $data, $format);
    }

    function purchase_status(){
        $existOffert = false;
        if ($this->status == "approved" || $this->status == "completed") {
            if(!empty($this->off)){
                $existOffert = true;
            }
			
            $this->hash_level = $this->consulta_items($prod);
            if(!$existOffert){
                if(!empty($this->hash_level)){
                    $hashlink = $this->obtener_hash_por_offert($name_subscription_plan);
                    if (!empty($hashlink)){
                        $redirectLink = $this->generar_enlace($hashlink);
                        header("Location: $redirectLink");
                    }
                }
            }else{
                if(!empty($this->hash_level)){
					$hashlink = $this->obtener_hash($this->hash_level);
                    if (!empty($hashlink)){
                        $redirectLink = $this->generar_enlace($hashlink) . "&transaction={$this->transaction}";
                        $this->save_transaction();
                        header("Location: $redirectLink");
                    }
                }
            }
        }else{
			var_dump($_POST);
		}
    }
}

// Si realiza una compra
if(isset($_POST) && isset($_POST['transaction'])){
    global $hotmart;
    global $miembropress;
	
	$hotmart->levelsTable = $miembropress->model->getLevelTable();
	$hotmart->hotmartTable = $miembropress->model->getHotmartTable();
	$hotmart->transaction = $_POST['transaction'];
	
	if(isset($_POST['prod'])){
		$hotmart->prod = $_POST['prod'];
	}

	if(isset($_POST['prod_name'])){
		$hotmart->prod_name = $_POST['prod_name'];
	}

	if(isset($_POST['off'])){
		$hotmart->off = $_POST['off'];
	}

	if(isset($_POST['email'])){
		$hotmart->email = $_POST['email'];
	}

	if(isset($_POST['name'])){
		$hotmart->name = $_POST['name'];
	}

	if(isset($_POST['doc'])){
		$hotmart->doc = $_POST['doc'];
	}

	if(isset($_POST['name_subscription_plan'])){
		$hotmart->name_subscription_plan = $_POST['name_subscription_plan'];
	}

	if(isset($_POST['recurrency_period'])){
		$hotmart->recurrency_period = $_POST['recurrency_period'];
	}

	if(isset($_POST['subscription_status'])){
		$hotmart->subscription_status = $_POST['subscription_status'];
	}

	if(isset($_POST['status'])){
		$hotmart->status = $_POST['status'];
    }
    
	if(isset($_POST['purchase_date'])){
		$hotmart->purchase_date = $_POST['purchase_date'];
    }
    
	if(isset($_POST['subscriber_code'])){
		$hotmart->subscriber_code = $_POST['subscriber_code'];
	}

	$hotmart->purchase_status();
}

// Si cancela una subscripcion
if(isset($_POST) && isset($_POST['subscriptionId']) && isset($_POST['subscriberCode'])){
    global $wpdb;
    global $hotmart;
    $hotmartTable = $miembropress->model->getHotmartTable();
    $subscriber_code = $_POST['subscriberCode'];
    $licenseTable = $wpdb->prefix . "licencias_member";

    if(isset($_POST['subscriptionPlanName'])){
        $subscriptionPlanName = $_POST['subscriptionPlanName'];
    }

    if(isset($_POST['productName'])){
        $productName = $_POST['productName'];
    }

    $data = array(
        'subscription_status' => 'expired',
        'status' => 'expired'
    );

    $where = array(
        'subscriber_code' => $subscriber_code,
        'name_subscription_plan' => $subscriptionPlanName,
        'prod_name' => $productName
    );
	
    $wpdb->update($hotmartTable, $data, $where);

    if(!empty($wpdb->get_var("SHOW TABLES LIKE '$licenseTable'"))){
        $userTable = $miembropress->model->getUserTable();
        $transaction = $wpdb->get_var( "SELECT transaction FROM $hotmartTable WHERE subscriber_code = '$subscriber_code'" );

        $user_id = $wpdb->get_var( "SELECT user_id FROM $userTable WHERE transaction = '$transaction'" );

        $data = array(
            'status' => '0',
        );
    
        $where = array(
            'user_id' => $user_id
        );
        
        $wpdb->update($licenseTable, $data, $where);
    }
}
?>
