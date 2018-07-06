<?php
if (isset($_GET['cloudpayments_ru']))
    {
    define ('CP_OK',0);
    define ('CP_ERR_INVOICEID',10);
    define ('CP_ERR_AMOUNT',11);
    define ('CP_ERR_OTHER',13);

    function cp_exit($code) {exit(json_encode(array('code' => $code)));}
    function cp_change_status($orderID, $suffix, $currentPaymentModule) {if ($status = $currentPaymentModule->_getSettingValue('CONF_CLOUDPAYMENTS_STATUS_AFTER_'.$suffix)) ostSetOrderStatusToOrder($orderID, $status);cp_exit(CP_OK);}

    if (isset($_SERVER['HTTP_CONTENT_HMAC']) && isset($_POST['InvoiceId']) && isset($_POST['Amount']) 
     && isset($_POST['Currency'])?$_POST['Currency'] == 'RUB':true)
        {
        $orderID = (int)$_POST['InvoiceId'];
        if ($order = db_fetch_assoc(db_query( "SELECT paymethod, order_amount FROM ".ORDERS_TABLE." WHERE orderID=$orderID LIMIT 1")))
            {
            $paymentMethod = payGetPaymentMethodById($order['paymethod']);                        // ������� ������� ������ ��� ����� ������
            $currentPaymentModule = modGetModuleObj($paymentMethod['module_id'], PAYMENT_MODULE); // ������� ������ ������ ��� ����� �������� ������
            $secret_key = $currentPaymentModule->_getSettingValue('CONF_CLOUDPAYMENTS_PASSWORD'); // ������� ��������� ���� (������ ��� API) ��� ����� ������ ������
            $order['order_amount'] = round($order['order_amount'],2);  // �������� ��������� ������ �� ���� �� ���� ������ ����� ������� (������ ��� ����� ���� ���� 1234.56789)
            if ($order['order_amount'] != $_POST['Amount']) cp_exit($errAmount); // ����� � ������� �� ��������� � ������ ������
            }
        else cp_exit(CP_ERR_INVOICEID);

        $raw_post = file_get_contents('php://input');                             // ������� "�����" POST (������ �� �������)
        $hash = base64_encode(hash_hmac('sha256', $raw_post, $secret_key, true)); // ������� ��� ��� ���� ������
        if ($_SERVER['HTTP_CONTENT_HMAC'] !== $hash) cp_exit(CP_ERR_OTHER);       // ������� � ����� �� ��������� Content-HMAC

        if($_GET['cloudpayments_ru'] == 'Pay')
            {
            if ($_POST['Status'] == 'Completed') cp_change_status($orderID, 'PAY', $currentPaymentModule);
            elseif ($_POST['Status'] == 'Authorized') cp_change_status($orderID, 'AUTH', $currentPaymentModule);
            else cp_exit(CP_ERR_OTHER);
            }
        elseif($_GET['cloudpayments_ru'] == 'Check') cp_exit(CP_OK);
        elseif($_GET['cloudpayments_ru'] == 'Confirm') cp_change_status($orderID, 'CONFIRM', $currentPaymentModule);
        elseif($_GET['cloudpayments_ru'] == 'Refund')  cp_change_status($orderID, 'REFUND', $currentPaymentModule);
        elseif($_GET['cloudpayments_ru'] == 'Cancel')  cp_change_status($orderID, 'CANCEL', $currentPaymentModule);
        }
    else cp_exit(CP_ERR_OTHER);
    }
?>
