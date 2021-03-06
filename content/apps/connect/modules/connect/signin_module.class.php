<?php
//
//    ______         ______           __         __         ______
//   /\  ___\       /\  ___\         /\_\       /\_\       /\  __ \
//   \/\  __\       \/\ \____        \/\_\      \/\_\      \/\ \_\ \
//    \/\_____\      \/\_____\     /\_\/\_\      \/\_\      \/\_\ \_\
//     \/_____/       \/_____/     \/__\/_/       \/_/       \/_/ /_/
//
//   上海商创网络科技有限公司
//
//  ---------------------------------------------------------------------------------
//
//   一、协议的许可和权利
//
//    1. 您可以在完全遵守本协议的基础上，将本软件应用于商业用途；
//    2. 您可以在协议规定的约束和限制范围内修改本产品源代码或界面风格以适应您的要求；
//    3. 您拥有使用本产品中的全部内容资料、商品信息及其他信息的所有权，并独立承担与其内容相关的
//       法律义务；
//    4. 获得商业授权之后，您可以将本软件应用于商业用途，自授权时刻起，在技术支持期限内拥有通过
//       指定的方式获得指定范围内的技术支持服务；
//
//   二、协议的约束和限制
//
//    1. 未获商业授权之前，禁止将本软件用于商业用途（包括但不限于企业法人经营的产品、经营性产品
//       以及以盈利为目的或实现盈利产品）；
//    2. 未获商业授权之前，禁止在本产品的整体或在任何部分基础上发展任何派生版本、修改版本或第三
//       方版本用于重新开发；
//    3. 如果您未能遵守本协议的条款，您的授权将被终止，所被许可的权利将被收回并承担相应法律责任；
//
//   三、有限担保和免责声明
//
//    1. 本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的；
//    2. 用户出于自愿而使用本软件，您必须了解使用本软件的风险，在尚未获得商业授权之前，我们不承
//       诺提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任；
//    3. 上海商创网络科技有限公司不对使用本产品构建的商城中的内容信息承担责任，但在不侵犯用户隐
//       私信息的前提下，保留以任何方式获取用户信息及商品信息的权利；
//
//   有关本产品最终用户授权协议、商业授权与技术服务的详细内容，均由上海商创网络科技有限公司独家
//   提供。上海商创网络科技有限公司拥有在不事先通知的情况下，修改授权协议的权力，修改后的协议对
//   改变之日起的新授权用户生效。电子文本形式的授权协议如同双方书面签署的协议一样，具有完全的和
//   等同的法律效力。您一旦开始修改、安装或使用本产品，即被视为完全理解并接受本协议的各项条款，
//   在享有上述条款授予的权力的同时，受到相关的约束和限制。协议许可范围以外的行为，将直接违反本
//   授权协议并构成侵权，我们有权随时终止授权，责令停止损害，并保留追究相关责任的权力。
//
//  ---------------------------------------------------------------------------------
//
defined('IN_ECJIA') or exit('No permission resources.');

class signin_module extends api_front implements api_interface {
	public function handleRequest(\Royalcms\Component\HttpKernel\Request $request) {		
		$this->authSession();
		$open_id      = $this->requestData('openid');
		$connect_code = $this->requestData('code');
		$device       = $this->device;
		$profile	  = $this->requestData('profile');
		
		if (empty($open_id) || empty($connect_code)) {
			return new ecjia_error('invalid_parameter', RC_Lang::get('system::system.invalid_parameter'));
		}
		
		/**
		 * $code
		 * login_weibo
		 * sns_qq
		 * sns_wechat
		 * login_mobile
		 * login_mail
		 * login_username
		 * login_alipay
		 * login_taobao
		 **/
		
// 		RC_Loader::load_app_class('connect_user', 'connect', false);
		$connect_user = new Ecjia\App\Connect\ConnectUser($connect_code, $open_id, 'user');
		//判断已绑定授权登录用户 直接登录
		if ($connect_user->checkUser()) {
			$connect_user_id = $connect_user->getUserId();
			$user_info = RC_Api::api('user', 'user_info', array('user_id' => $connect_user_id));
			RC_Loader::load_app_class('integrate', 'user', false);
			$user = integrate::init_users();
			$user->set_session($user_info['user_name']);
			$user->set_cookie($user_info['user_name']);
			
			$data = array(
				'profile' => serialize($profile)
			);
			RC_Model::model('connect/connect_user_model')->where(array('connect_code' => $connect_user->getConnectCode(), 'user_type' => 'user', 'open_id' => $connect_user->getOpenId(), 'user_id' => $_SESSION['user_id']))->update($data);
			
			/* 获取远程用户头像信息*/
			RC_Api::api('connect', 'update_user_avatar', array('avatar_url' => $profile['avatar_img']));
		} else {
			return new ecjia_error('connect_no_userbind', '请关联或注册一个会员用户！');
			//新用户注册并登录
// // 			$username = $connect_user->get_username();
// 			$username = $username . rc_random(4, 'abcdefghijklmnopqrstuvwxyz0123456789');
// 			$password = md5(rc_random(9, 'abcdefghijklmnopqrstuvwxyz0123456789'));
// // 			$email = $connect_user->get_email();
// 			$email = rc_random(8, 'abcdefghijklmnopqrstuvwxyz0123456789').'@'.$connect_code.'.com';
// 			$user = integrate::init_users();
// 			$result = $user->add_user($username, $password, $email);
// 			$user->set_session($username);
// 			$user->set_cookie($username);
// 			$curr_time = RC_Time::gmtime();
// 			$data = array(
// 					'connect_code'	=> $connect_user->connect_code,
// 					'open_id'		=> $connect_user->open_id,
// 					'create_at'     => $curr_time,
// 					'user_id'		=> $_SESSION['user_id']
// 			);
// 			RC_Model::model('connect/connect_user_model')->insert($data);
		}
		
		// 1、同步会员信息
		// 2、修正咨询信息	
		
		feedback_batch_userid($_SESSION['user_id'], $_SESSION['user_name'], $device);

		RC_Loader::load_app_func('admin_user', 'user');
		$user_info = EM_user_info($_SESSION['user_id']);
		
		update_user_info(); // 更新用户信息
		RC_Loader::load_app_func('cart','cart');
		recalculate_price(); // 重新计算购物车中的商品价格
		
		//修正关联设备号
		$result = ecjia_app::validate_application('mobile');
		if (!is_ecjia_error($result)) {
			if (!empty($device['udid']) && !empty($device['client']) && !empty($device['code'])) {
				$db_mobile_device = RC_Model::model('mobile/mobile_device_model');
				$device_data = array(
					'device_udid'	=> $device['udid'],
					'device_client'	=> $device['client'],
					'device_code'	=> $device['code'],
					'user_type'		=> 'user',
				);
				$db_mobile_device->where($device_data)->update(array('user_id' => $_SESSION['user_id'], 'update_time' => RC_Time::gmtime()));
			}
		}
		
		$out = array(
		    'token' => RC_Session::session_id(),
		    'user'	=> $user_info
		);
		return $out;
	}
}


/**
 * 修正咨询信息
 * @param string $user_id
 * @param string $device
 */
function feedback_batch_userid($user_id, $user_name, $device) {
	$device_udid	  = $device['udid'];
	$device_client	  = $device['client'];
	$db_term_relation = RC_Model::model('term_relationship_model');
	 
	$object_id = $db_term_relation->where(array(
		'object_type'	=> 'ecjia.feedback',
		'object_group'	=> 'feedback',
		'item_key2'		=> 'device_udid',
		'item_value2'	=> $device_udid 
	))->get_field('object_id', true);
	//更新未登录用户的咨询
	$db_term_relation->where(array('item_key2' => 'device_udid', 'item_value2' => $device_udid))->update(array('item_key2' => '', 'item_value2' => ''));
	 
	if (!empty($object_id)) {
		$db = RC_Model::model('feedback/feedback_model');
		$db->where(array('msg_id' => $object_id, 'msg_area' => '4'))->update(array('user_id' => $user_id, 'user_name' => $user_name));
		$db->where(array('parent_id' => $object_id, 'msg_area' => '4'))->update(array('user_id' => $user_id, 'user_name' => $user_name));
	}
}

// end