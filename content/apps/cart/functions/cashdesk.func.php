<?php

/**
 * 获得订单中的费用信息
 *
 * @access  public
 * @param   array   $order
 * @param   array   $goods
 * @param   array   $consignee
 * @param   bool    $is_gb_deposit  是否团购保证金（如果是，应付款金额只计算商品总额和支付费用，可以获得的积分取 $gift_integral）
 * @return  array
 */
function cashdesk_order_fee($order, $goods, $consignee = array()) {
	
	RC_Logger::getLogger('test')->info('测试收银购物流111');
	RC_Logger::getLogger('test')->info($order);
	RC_Logger::getLogger('test')->info($goods);
	RC_Logger::getLogger('test')->info($consignee);
	RC_Logger::getLogger('test')->info('测试收银购物流222');
	
    RC_Loader::load_app_func('global','goods');
    RC_Loader::load_app_func('cart','cart');
    $db 	= RC_Loader::load_app_model('cart_model', 'cart');
    $dbview = RC_Loader::load_app_model('cart_exchange_viewmodel', 'cart');
    /* 初始化订单的扩展code */
    if (!isset($order['extension_code'])) {
        $order['extension_code'] = '';
    }

    //     TODO: 团购等促销活动注释后暂时给的固定参数
    $order['extension_code'] = '';
    $group_buy ='';
    //     TODO: 团购功能暂时注释
    //     if ($order['extension_code'] == 'group_buy') {
    //         $group_buy = group_buy_info($order['extension_id']);
    //     }

    $total  = array('real_goods_count' => 0,
        'gift_amount'      => 0,
        'goods_price'      => 0,
        'market_price'     => 0,
        'discount'         => 0,
        'pack_fee'         => 0,
        'card_fee'         => 0,
        'shipping_fee'     => 0,
        'shipping_insure'  => 0,
        'integral_money'   => 0,
        'bonus'            => 0,
        'surplus'          => 0,
        'cod_fee'          => 0,
        'pay_fee'          => 0,
        'tax'              => 0
    );
    $weight = 0;
    $shop_type = RC_Config::load_config('site', 'SHOP_TYPE');
    /* 商品总价 */
    foreach ($goods AS $key => $val) {
        /* 统计实体商品的个数 */
        if ($val['is_real']) {
            $total['real_goods_count']++;
        }

        if ($val['extension_code'] == 'bulk') {
            //散装价格x重量（数量/1000）
            $total['goods_price'] += $val['goods_price'] * $val['goods_number'] / 1000;
            $total['goods_price'] = formated_price_bulk($total['goods_price']);
            $total['market_price'] += $val['market_price'] * $val['goods_number'] / 1000;
            $total['market_price'] = formated_price_bulk($total['market_price']);
        } else {
            $total['goods_price']  += $val['goods_price'] * $val['goods_number'];
            $total['market_price'] += $val['market_price'] * $val['goods_number'];
        }

        $area_id = $consignee['province'];
        //多店铺开启库存管理以及地区后才会去判断
        if ( $area_id > 0 && $shop_type == 'b2b2c') {
            $warehouse_db = RC_Loader::load_app_model('warehouse_model', 'warehouse');
            $warehouse = $warehouse_db->where(array('regionId' => $area_id))->find();
            $warehouse_id = $warehouse['parent_id'];
            $goods[$key]['warehouse_id'] = $warehouse_id;
            $goods[$key]['area_id'] = $area_id;
        } else {
            $goods[$key]['warehouse_id'] = 0;
            $goods[$key]['area_id'] 	 = 0;
        }
    }

    $total['saving']    = $total['market_price'] - $total['goods_price'];
    $total['save_rate'] = $total['market_price'] ? round($total['saving'] * 100 / $total['market_price']) . '%' : 0;

    $total['goods_price_formated']  = price_format($total['goods_price'], false);
    $total['market_price_formated'] = price_format($total['market_price'], false);
    $total['saving_formated']       = price_format($total['saving'], false);

    /* 折扣 */
    if ($order['extension_code'] != 'group_buy') {
        RC_Loader::load_app_func('cart','cart');
        $discount = compute_discount();
        $total['discount'] = $discount['discount'];
        if ($total['discount'] > $total['goods_price']) {
            $total['discount'] = $total['goods_price'];
        }
    }
    $total['discount_formated'] = price_format($total['discount'], false);

    /* 税额 */
    if (!empty($order['need_inv']) && $order['inv_type'] != '') {
        /* 查税率 */
        $rate = 0;
        $invoice_type=ecjia::config('invoice_type');
        foreach ($invoice_type['type'] as $key => $type) {
            if ($type == $order['inv_type']) {
                $rate_str = $invoice_type['rate'];
                $rate = floatval($rate_str[$key]) / 100;
                break;
            }
        }
        if ($rate > 0) {
            $total['tax'] = $rate * $total['goods_price'];
        }
    }
    $total['tax_formated'] = price_format($total['tax'], false);
    //	TODO：暂时注释
    /* 包装费用 */
    //     if (!empty($order['pack_id'])) {
    //         $total['pack_fee']      = pack_fee($order['pack_id'], $total['goods_price']);
    //     }
    //     $total['pack_fee_formated'] = price_format($total['pack_fee'], false);

    //	TODO：暂时注释
    //    /* 贺卡费用 */
    //    if (!empty($order['card_id'])) {
    //        $total['card_fee']      = card_fee($order['card_id'], $total['goods_price']);
    //    }
    $total['card_fee_formated'] = price_format($total['card_fee'], false);

    RC_Loader::load_app_func('admin_bonus','bonus');
    /* 红包 */
    if (!empty($order['bonus_id'])) {
        $bonus          = bonus_info($order['bonus_id']);
        $total['bonus'] = $bonus['type_money'];
    }
    $total['bonus_formated'] = price_format($total['bonus'], false);

    /* 线下红包 */
    if (!empty($order['bonus_kill'])) {
        $bonus  = bonus_info(0, $order['bonus_kill']);
        $total['bonus_kill'] = $order['bonus_kill'];
        $total['bonus_kill_formated'] = price_format($total['bonus_kill'], false);
    }

    $total['shipping_fee']		= 0;
    $total['shipping_insure']	= 0;
    $total['shipping_fee_formated']    = price_format($total['shipping_fee'], false);
    $total['shipping_insure_formated'] = price_format($total['shipping_insure'], false);

    // 活动优惠总金额
    $discount_amount = compute_discount_amount();
    // 红包和积分最多能支付的金额为商品总额
    //$max_amount 还需支付商品金额=商品金额-红包-优惠-积分
    $max_amount = $total['goods_price'] == 0 ? $total['goods_price'] : $total['goods_price'] - $discount_amount;


    /* 计算订单总额 */
    if ($order['extension_code'] == 'group_buy' && $group_buy['deposit'] > 0) {
        $total['amount'] = $total['goods_price'];
    } else {
        $total['amount'] = $total['goods_price'] - $total['discount'] + $total['tax'] + $total['pack_fee'] + $total['card_fee'] + $total['shipping_fee'] + $total['shipping_insure'] + $total['cod_fee'];
        // 减去红包金额
        $use_bonus	= min($total['bonus'], $max_amount); // 实际减去的红包金额
        if(isset($total['bonus_kill'])) {
            $use_bonus_kill   = min($total['bonus_kill'], $max_amount);
            $total['amount'] -=  $price = number_format($total['bonus_kill'], 2, '.', ''); // 还需要支付的订单金额
        }

        $total['bonus']   			= ($total['bonus'] > 0) ? $use_bonus : 0;
        $total['bonus_formated'] 	= price_format($total['bonus'], false);

        $total['amount'] -= $use_bonus; // 还需要支付的订单金额
        $max_amount      -= $use_bonus; // 积分最多还能支付的金额
    }
    /* 余额 */
    $order['surplus'] = $order['surplus'] > 0 ? $order['surplus'] : 0;
    if ($total['amount'] > 0) {
        if (isset($order['surplus']) && $order['surplus'] > $total['amount']) {
            $order['surplus'] = $total['amount'];
            $total['amount']  = 0;
        } else {
            $total['amount'] -= floatval($order['surplus']);
        }
    } else {
        $order['surplus'] = 0;
        $total['amount']  = 0;
    }
    $total['surplus'] 			= $order['surplus'];
    $total['surplus_formated'] 	= price_format($order['surplus'], false);

    /* 积分 */
    $order['integral'] = $order['integral'] > 0 ? $order['integral'] : 0;
    if ($total['amount'] > 0 && $max_amount > 0 && $order['integral'] > 0) {
        $integral_money = value_of_integral($order['integral']);
        // 使用积分支付
        $use_integral            = min($total['amount'], $max_amount, $integral_money); // 实际使用积分支付的金额
        $total['amount']        -= $use_integral;
        $total['integral_money'] = $use_integral;
        $order['integral']       = integral_of_value($use_integral);
    } else {
        $total['integral_money'] = 0;
        $order['integral']       = 0;
    }
    $total['integral'] 			 = $order['integral'];
    $total['integral_formated']  = price_format($total['integral_money'], false);

    /* 保存订单信息 */
    $_SESSION['flow_order'] = $order;
    $se_flow_type = isset($_SESSION['flow_type']) ? $_SESSION['flow_type'] : '';

    /* 支付费用 */
    if (!empty($order['pay_id']) && ($total['real_goods_count'] > 0 || $se_flow_type != CART_EXCHANGE_GOODS)) {
        $total['pay_fee']      	= pay_fee($order['pay_id'], $total['amount'], $shipping_cod_fee);
    }
    $total['pay_fee_formated'] 	= price_format($total['pay_fee'], false);
    $total['amount']           += $total['pay_fee']; // 订单总额累加上支付费用
    $total['amount_formated']  	= price_format($total['amount'], false);

    /* 取得可以得到的积分和红包 */
    if ($order['extension_code'] == 'group_buy') {
        $total['will_get_integral'] = $group_buy['gift_integral'];
    } elseif ($order['extension_code'] == 'exchange_goods') {
        $total['will_get_integral'] = 0;
    } else {
        $total['will_get_integral'] = get_give_integral($goods);
    }

    $total['will_get_bonus']        = $order['extension_code'] == 'exchange_goods' ? 0 : price_format(get_total_bonus(), false);
    $total['formated_goods_price']  = price_format($total['goods_price'], false);
    $total['formated_market_price'] = price_format($total['market_price'], false);
    $total['formated_saving']       = price_format($total['saving'], false);

    if ($order['extension_code'] == 'exchange_goods') {
        if ($_SESSION['user_id']) {
            $exchange_integral = $dbview->join('exchange_goods')->where(array('c.user_id' => $_SESSION['user_id'] , 'c.rec_type' => CART_EXCHANGE_GOODS , 'c.is_gift' => 0 ,'c.goods_id' => array('gt' => 0)))->group('eg.goods_id')->sum('eg.exchange_integral');
        } else {
            $exchange_integral = $dbview->join('exchange_goods')->where(array('c.session_id' => SESS_ID , 'c.rec_type' => CART_EXCHANGE_GOODS , 'c.is_gift' => 0 ,'c.goods_id' => array('gt' => 0)))->group('eg.goods_id')->sum('eg.exchange_integral');
        }
        $total['exchange_integral'] = $exchange_integral;
    }

    return $total;
}