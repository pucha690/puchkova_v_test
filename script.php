<?php

$input = [
    "submit_url" => "https://something/submit.php",
    "auth_url"   => "https://home/auth.php"
];

function query($sql) {}

function secure_key($service_type, $customer_id) {}

function order_submit($url) {}

function getOrders() {
    $sql = "
       WITH order_list 
        AS (
            SELECT orders.order_id 
                FROM orders
                ORDER BY orders.order_id ASC
                LIMIT 100
            )

       SELECT 
            order_charges.order_id,
            SUM(order_charges.value) AS amount,
            array_to_string(array_agg(DISTINCT order_charges.price_entity_d 
                ORDER BY order_charges.price_entity_d), ',') AS price_entities,
            o.customer_id,
            o.service_id
            
       FROM 
            order_charges
        
       JOIN 
            orders o ON order_charges.order_id = o.order_id
        
       WHERE 
            order_charges.order_id IN (SELECT order_id FROM order_list)
        
       GROUP BY 
            order_charges.order_id, o.customer_id, o.service_id
    ";

    return query($sql);
}

function getAuthUrl($order, $input_auth_url) {
    $key = secure_key($order->service_id, $order->customer_id);

    return $input_auth_url . '?order_id=' . $order->order_id . '&secure_key=' . $key;
}

function constructUrl($order) {
    $g_input  = $GLOBALS['input'];
    $url      = $g_input['submit_url'] . '?order_id=' . $order->order_id . '&amount=' . $order->amount . '&prices=' . $order->price_entities;
    $auth_url = getAuthUrl($order, $g_input['auth_url']);

    return $url . '&auth=' . urlencode($auth_url);
}

function fillStructure($order, $sid, &$result) {
    $url = constructUrl($order);

    $result[$sid]['sum'] += $order->amount;

    $invoice_id = 0;

    try {
        $invoice_id = order_submit($url);
    } catch (\Exception $e) {
        $result[$sid]['has_error'] = true;
    }

    array_push($result[$sid]['invoice_id'], $invoice_id);
}

function getStructure($raw_orders) {
    $result = [];

    if (!is_array($raw_orders)) return $result;

    foreach ($raw_orders as $raw_order) {
        $order = (object)$raw_order;
        $sid   = $order->service_id;

        if (!isset($result[$sid])) {
            $result[$sid] = getDefaultServiceIdStructure();
        }

        fillStructure($order, $sid, $result);
    }

    return $result;
}

function getDefaultServiceIdStructure() {
    return [
        'sum'        => 0,
        'invoice_id' => [],
        'has_error'  => false
    ];
}

$orders    = getOrders();
$structure = getStructure($orders);

return json_encode($structure);
