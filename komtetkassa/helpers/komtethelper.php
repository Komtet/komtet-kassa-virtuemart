<?php
// запрещаем доступ извне
defined('_JEXEC') or die;

use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Payment;


class komtetHelper
{
	public function fiscalize($order, $params)
	{

		$component_path = JPATH_PLUGINS.'/system/komtetkassa';

		include_once $component_path.'/helpers/kassa/QueueManager.php';
		include_once $component_path.'/helpers/kassa/Position.php';
		include_once $component_path.'/helpers/kassa/Check.php';
		include_once $component_path.'/helpers/kassa/Client.php';
		include_once $component_path.'/helpers/kassa/Vat.php';
		include_once $component_path.'/helpers/kassa/Payment.php';
		include_once $component_path.'/helpers/kassa/Exception/SdkException.php';

		$db = JFactory::getDbo();

		$order_fics_status = new stdClass();
		$order_fics_status->order_id = $order['details']['BT']->order_number;
		$order_fics_status->status='pending';
		$result = $db->insertObject('#__virtuemart_order_fiscalization_status', $order_fics_status);

		$positions = $order['items'];

		$payment = Payment::createCard(floatval($order['details']['BT']->order_total));

		switch ($params['sno']) {
		    case 'osn':
		        $parsed_sno = 0;
		        break;
		    case 'usn_dohod':
		        $parsed_sno = 1;
		        break;
		    case 'usn_dohod_rashod':
		        $parsed_sno = 2;
		        break;
	        case 'esn':
		        $parsed_sno = 4;
		        break;
	        case 'patent':
		        $parsed_sno = 5;
		        break;
	        default:
	        	$parsed_sno = intval($params['sno']);
		}

		$method = $order['details']['BT']->order_status == 'R' ? Check::INTENT_SELL_RETURN : Check::INTENT_SELL;

		$check = new Check($order['details']['BT']->order_number, $order['details']['BT']->email, $method, $parsed_sno);
		$check->setShouldPrint($params['is_print_check']);

        if ($params->get('is_internet')) {
            $check->setInternet(true);
        }

		$check->addPayment($payment);

		$vat = new Vat($params['vat']);

		foreach( $positions as $position )
		{
			$positionObj = new Position($position->order_item_name,
										floatval($position->product_discountedPriceWithoutTax),
										floatval($position->product_quantity),
										$position->product_quantity*$position->product_discountedPriceWithoutTax,
										floatval($position->prices->discountAmount),
										$vat);

			$check->addPosition($positionObj);
		}

		if (floatval($order['details']['BT']->order_shipment) > 0) {
			$shippingPosition = new Position("Доставка",
											 floatval($order['details']['BT']->order_shipment),
											 1,
											 floatval($order['details']['BT']->order_shipment),
											 0,
											 $vat);
			$check->addPosition($shippingPosition);
		}

		// $packagePosition = new Position("Упаковка",
		// 								 floatval($position->order_package),
		// 								 1,
		// 								 floatval($position->order_package),
		// 								 0,
		// 								 $vat);
		// $check->addPosition($packagePosition);

		$client = new Client($params['shop_id'], $params['secret']);
		$queueManager = new QueueManager($client);

		$queueManager->registerQueue('print_que', $params['queue_id']);

		try {
		    $queueManager->putCheck($check, 'print_que');
		} catch (SdkException $e) {
		    echo $e->getMessage();
		}
	}
}
