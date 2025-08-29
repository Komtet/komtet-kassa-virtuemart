<?php

JLoader::register('komtetHelper', JPATH_PLUGINS.'/system/komtetkassa/helpers/komtethelper.php');

class plgSystemKomtetkassa extends JPlugin
{

    protected $autoloadLanguage = true;

    public function isShouldFiscalize($pm_system_id)
    {
        $pm_methods_ids = explode(',', $this->params['pm_methods']);
        foreach ($pm_methods_ids as $pm_m_id)
        {
            $pm_m_id = trim($pm_m_id);
        }

        if (in_array($pm_system_id, $pm_methods_ids))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function fiscalize($order, $params)
    {
        komtetHelper::fiscalize($order, $params);
        return;
    }

    public function plgVmOnUpdateOrderPayment(&$data, $old_order_status)
    {
        require_once(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        $virtuemart_model_orders = new VirtueMartModelOrders;
        $order = $virtuemart_model_orders->getOrder($data->virtuemart_order_id);

        /*
            P - PENDING !
            U - CONFIRMED_BY_SHOPPER
            C - CONFIRMED
            X - CANCELLED !
            R - REFUNDED
            S - SHIPPED !
            F - COMPLETED
            D - DENIED !
        */
        if ( in_array($data->order_status, array('U', 'C', 'R', 'F')) && $this->isShouldFiscalize($order['details']['BT']->virtuemart_paymentmethod_id))
        {
            $order['details']['BT']->order_status = $data->order_status;
            $this->fiscalize($order, $this->params);
        }
        return true;
    }
}