<?php
namespace app\user\controller;//定义以下类所在的命名空间
use think\View;//引用命名空间
use think\Controller;
use think\Session;
use think\Db;
use app\common\model\Users;

class Orders extends Controller//定义一个类（控制器）,继承TP5的Controller类
{
	//显示购物车
	public function cart()
	{   
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "login"); // 未登录，跳转到登录页
		}
		// 查询购物车商品信息
		$items = Db::table('shoppingcart')
			->alias('s')
			->join('products p', 's.pid = p.pid')
			->where('s.uname', $uname)
			->field('p.pId, p.pName, s.number, p.pImg, p.pPrice, p.pPrice * s.number as totalPrice')
			->select();
		$this->assign('items', $items); // 为前台模板变量绑定值

		$sumPrice = 0;
		foreach ($items as $item) { // 计算总价
			$sumPrice += $item['totalPrice'];
		}
		$this->assign('sumPrice', $sumPrice); // 为前台模板变量绑定值
		return view(); // 返回视图	
	}
    	//修改购物车(未对数据进行有效性验证，请自行实现)
		public function updateCar($id = "", $num = 1)
		{
			// 传递过来的$id的值形如number12，后面的数字才是真正的id值
			$id = substr($id, 6); // 取出商品的id
			$uname = Session::get("username"); // 取已登录的用户名
			if (empty($uname)) {
				return json(['status' => 'error', 'message' => '请先登录']);
			}
			if ($num < 1) {
				return json(['status' => 'error', 'message' => '数量必须大于0']);
			}
		
			// 检查库存
			$product = Db::name('Products')->where('pId', $id)->find();
			if ($product['pStock'] < $num) {
				return json(['status' => 'error', 'message' => '库存不足']);
			}
		
			try {
				// 修改记录
				Db::name('shoppingcart')->where('uname', $uname)->where('pid', $id)->update(['number' => $num]);
				return json(['status' => 'ok']);
			} catch (\Exception $e) {  // 捕获异常
				return json(['status' => 'error', 'message' => '修改失败']);
			}
		}
		

    	//删除购物车(未对数据进行有效性验证，请自行实现)
	public function deleteItem($id = "")
	{
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			return json(['status' => 'error', 'message' => '请先登录']);
		}
		try {
			// 删除记录
			Db::name('shoppingcart')->where('uname', $uname)->where('pid', $id)->delete();
			return json(['status' => 'ok']);
		} catch (\Exception $e) {  // 捕获异常
			return json(['status' => 'error', 'message' => '删除失败']);
		}
	}

    	//创建订单
	public function createOrder()
	{ 
		$uname=Session::get("username");//取已登录的用户名
		if(empty($uname)){
			$this->error("请先登录","user/index/login");//未登录，跳转到登录页
		}
		$orderDate=date("Y-m-d H:i:s");//下单时间日期
		$status="待付款"; //默认订单状态
		$orderId=(string)date("Y-m-d-H-i-s").mt_rand(100,999);//在此订单号由日期加3位随机数组成

		//查订单的总价格
        //select sum(p.pPrice*number) sumPrice  from shoppingcart s inner join products p on  p.pId=s.pId and uname='$uname'
		$sum=Db::table('shoppingcart')->alias('s')->join('products p','s.pid = p.pid')->where('s.uname',$uname)->field('sum(p.pPrice*s.number) as sumPrice')->find();
		$orderData = ['orderId' => $orderId,'uName' => $uname,'orderDate' => $orderDate, 'totalPrice' => $sum['sumPrice'],  'status'=>$status];//构造数据
        //select p.pId, p.pName,number, p.pImg, p.pPrice, p.pPrice*number totalPrice  from shoppingcart s inner join products p on  p.pId=s.pId and uname='$uname'
		$items=Db::table('shoppingcart')->alias('s')->join('products p','s.pid = p.pid')->where('s.uname',$uname)->field('p.pId, p.pName,s.number, p.pImg, p.pPrice, p.pPrice*s.number as totalPrice,p.pSales,p.pStock')->select();//查购物车
        if(empty($items)){
			$this->error("购物车中没有商品不能下单");
		} 		
		// 启动事务,手动控制事务处理
		Db::startTrans();
		try{
			Db::name('orders')->insert($orderData);
			
		    foreach($items as $item){
				//构造数据
				// 更新库存和销量
				Db::table('products')->where('pId', $item['pId'])->update([
					'pStock' => $item['pStock'] - $item['number'], // 库存减少
					'pSales' => $item['pSales'] + $item['number']  // 销量增加
				]);
				$detailData = ['orderId' => $orderId,'totalPrice' => $item['totalPrice'], 'pId' => $item['pId'],'number'=>$item['number']];
				Db::name('orderdetails')->insert($detailData);		//增加记录	
			}
			//删除该用户的购物车中的所有记录
			Db::name('shoppingcart')->where('uname',$uname)->delete();
		    Db::commit(); // 提交事务   
		} catch (\Exception $e) {    
		    Db::rollback();// 回滚事务
		    $this->error($e->getMessage());
		}  		
		$this->success("下单成功","myorders");//下单成功转到我的订单页面
	}
	public function createInstantOrder()
	{
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			return json(['status' => 'error', 'message' => '请先登录']);
		}

		$orderDate = date("Y-m-d H:i:s"); // 下单时间日期
		$status = "待付款"; // 默认订单状态
		$orderId = (string)date("Y-m-d-H-i-s") . mt_rand(100, 999); // 在此订单号由日期加3位随机数组成

		$productId = input('post.productId');
		$quantity = input('post.quantity', 1, 'intval');

		// 检查库存是否充足
		$product = Db::table('products')->where('pId', $productId)->find();
		if ($product['pStock'] < $quantity) {
			return json(['status' => 'error', 'message' => "商品{$product['pName']}库存不足"]);
		}

		// 计算订单总价格
		$totalPrice = $product['pPrice'] * $quantity;

		$orderData = [
			'orderId' => $orderId,
			'uName' => $uname,
			'orderDate' => $orderDate,
			'totalPrice' => $totalPrice,
			'status' => $status
		];

		// 启动事务, 手动控制事务处理
		Db::startTrans();
		try {
			Db::name('orders')->insert($orderData);

			// 构造订单详情数据
			$detailData = [
				'orderId' => $orderId,
				'totalPrice' => $totalPrice,
				'pId' => $productId,
				'number' => $quantity
			];
			Db::name('orderdetails')->insert($detailData); // 增加记录

			// 更新库存和销量
			Db::table('products')->where('pId', $productId)->update([
				'pStock' => $product['pStock'] - $quantity, // 库存减少
				'pSales' => $product['pSales'] + $quantity  // 销量增加
			]);

			Db::commit(); // 提交事务
		} catch (\Exception $e) {
			Db::rollback(); // 回滚事务
			return json(['status' => 'error', 'message' => $e->getMessage()]);
		}

		return json(['status' => 'success', 'orderId' => $orderId]);
	}

	public function orderDelete($id) {
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "user/index/login"); // 未登录，跳转到登录页
		}
	
		// 检查订单是否属于当前用户
		$order = Db::name('orders')->where('orderId', $id)->where('uName', $uname)->find();
		if (empty($order)) {
			$this->error("订单不存在或无权删除");
		}
	
		// 获取订单详情，恢复库存和销量
		$orderDetails = Db::name('orderdetails')->where('orderId', $id)->select();
		foreach ($orderDetails as $detail) {
			// 获取当前库存和销量
			$product = Db::table('products')->where('pId', $detail['pId'])->find();
	
			// 计算新的库存和销量
			$newStock = $product['pStock'] + $detail['number'];
			$newSales = $product['pSales'] - $detail['number'];
	
			// 更新库存和销量
			Db::table('products')->where('pId', $detail['pId'])->update([
				'pStock' => $newStock,
				'pSales' => $newSales
			]);
		}
	
		// 删除订单详情
		Db::name('orderdetails')->where('orderId', $id)->delete();
	
		// 删除订单
		Db::name('orders')->where('orderId', $id)->delete();
	
		$this->success("订单删除成功", "myorders"); // 删除成功转到我的订单页面
	}
	
	
	
    	//显示订单
		public function myorders() {
			// 创建 Index 控制器的实例
			$indexController = new Index();
		
			// 调用 Index 控制器中的方法
			$categories = Db::name('class')->select();
			$categoryTree = $indexController->buildCategoryTree($categories);
			$categoryHtml = $indexController->renderCategories($categoryTree);
			// 将分类数据传递给模板
			$this->assign('categoryHtml', $categoryHtml);
		
			$uname = Session::get("username"); // 取已登录的用户名
			if (empty($uname)) {
				$this->error("请先登录", "user/index/login"); // 未登录，跳转到登录页
			}
		
			$status = input('get.status', 'all'); // 获取分类状态，默认为'all'
			$query = Db::name('orders')->where('uname', $uname);
		
			if ($status !== 'all') {
				$query->where('status', $status);
			}
		
			$items = $query->order('orderId', 'desc')->select();
			$this->assign('items', $items); // 为前台模板变量绑定值
			$this->assign('currentStatus', $status); // 传递当前分类状态给模板
		
			return view(); // 返回视图
		}
		
    		//显示订单详情
	public function orderDetail($id='')
	{
		// 创建 Index 控制器的实例
        $indexController = new Index();

        // 调用 Index 控制器中的方法
        $categories = Db::name('class')->select();
        $categoryTree = $indexController->buildCategoryTree($categories);
        $categoryHtml = $indexController->renderCategories($categoryTree);
        // 将分类数据传递给模板
        $this->assign('categoryHtml', $categoryHtml);
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "user/index/login"); // 未登录，跳转到登录页
		}

		$items = Db::table('orderdetails')
			->alias('od')
			->join('products p', 'od.pId = p.pId')
			->join('orders o', 'od.orderId = o.orderId')
			->where('od.orderId', $id)
			->field('p.pId, p.pName, od.orderId, od.number, od.totalPrice, p.pImg, p.pPrice, p.pTime, (SELECT COUNT(*) FROM comments c WHERE c.productId = od.pId AND c.orderId = od.orderId) as commentCount')
			->select();

		$this->assign('items', $items); // 为前台模板变量绑定值

		$sumPrice = 0;
		foreach ($items as $item) { // 计算总价
			$sumPrice += $item['totalPrice'];
		}
		$this->assign('sumPrice', $sumPrice); // 为前台模板变量绑定值
		$this->assign('uname', $uname);

		return view(); // 返回视图
	}


	public function payment($id = '')
	{
		$this->assign('id',$id);
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "login"); // 未登录，跳转到登录页
		}
		$this->assign('uname',$uname);
		// 查询订单信息
		$order = Db::table('orders')->where('orderId', $id)->where('uName', $uname)->find();
		if (empty($order)) {
			$this->error("订单不存在或无权限查看此订单1");
		}

		// 查询订单详情
		$items = Db::table('orderdetails')
			->alias('od')
			->join('products p', 'od.pid = p.pid')
			->where('od.orderId', $id)
			->field('p.pId, p.pName, od.number as number, p.pImg, p.pPrice, p.pPrice * od.number as totalPrice')
			->select();
		$this->assign('items', $items); // 为前台模板变量绑定值

		$sumPrice = 0;
		foreach ($items as $item) { // 计算总价
			$sumPrice += $item['totalPrice'];
		}
		$this->assign('sumPrice', $sumPrice); // 为前台模板变量绑定值
		$this->assign('order', $order); // 为前台模板绑定订单信息
		return view(); // 返回视图
	}
	public function submitPayment()
	{
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "login"); // 未登录，跳转到登录页
		}

		$orderId = input('post.orderId');
		$name = input('post.name');
		$phone = input('post.phone');
		$email = input('post.email');
		$address = input('post.address');
		$paymentMethod = input('post.paymentMethod');
		$message = input('post.message');

		// 检查订单是否存在并且属于当前用户
		$order = Db::table('orders')->where('orderId', $orderId)->where('uName', $uname)->find();
		if (empty($order)) {
			$this->error("订单不存在或无权限查看此订单");
		}

		// 更新订单信息
		$updateData = [
			'phone' => $phone,
			'email' => $email,
			'address' => $address,
			'paymentMethod' => $paymentMethod,
			'message' => $message,
			'status' => '待发货' // 更新订单状态为未发货
		];
		Db::table('orders')->where('orderId', $orderId)->update($updateData);

		// 获取订单详情以更新库存和销量
		$orderDetails = Db::table('orderdetails')->where('orderId', $orderId)->select();

		foreach ($orderDetails as $detail) {
			$pId = $detail['pId'];
			$quantity = $detail['number'];

			// 检查库存是否充足
			$product = Db::table('products')->where('pId', $pId)->find();
			if ($product['pStock'] < $quantity) {
				$this->error("商品{$product['pName']}库存不足，无法完成支付");
			}

			// 更新库存和销量
			Db::table('products')->where('pId', $pId)->update([
				'pStock' => $product['pStock'] - $quantity, // 库存减少
				'pSales' => $product['pSales'] + $quantity  // 销量增加
			]);
		}

		// 返回支付成功页面或跳转到其他页面
		$this->success('支付成功', 'orders/myorders');
	}
	

	public function receive($id) {
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "login"); // 未登录，跳转到登录页
		}
	
		// 检查订单是否存在且属于当前用户
		$order = Db::name('orders')->where(['orderId' => $id, 'uname' => $uname])->find();
		if (empty($order)) {
			$this->error("订单不存在或无权操作此订单");
		}
	
		// 更新订单状态为未评价
		Db::name('orders')->where('orderId', $id)->update(['status' => '待评价']);
	
		$this->success("确认收货成功", "orders/myorders"); // 跳转到我的订单页面
	}
	public function orderComment($orderId)
	{
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			$this->error("请先登录", "login"); // 未登录，跳转到登录页
		}

		if (request()->isPost()) {
			// 检查是否已经评论过
			$existingComment = Db::name('ordercomments')
				->where('orderId', $orderId)
				->where('uname', $uname)
				->find();

			if ($existingComment) {
				$this->error("您已经对该订单进行了评论");
			}

			$data = [
				'orderId' => $orderId,
				'uname' => $uname,
				'message' => input('post.message'),
				'rating' => input('post.rating'),
				'created_at' => date('Y-m-d H:i:s'),
			];
			Db::name('ordercomments')->insert($data);

			// 更新订单状态为交易完成
			Db::name('orders')
				->where('orderId', $orderId)
				->update(['status' => '交易完成']);

			$this->success("评论提交成功", url('orders/myorders', ['id' => $orderId])); // 跳转到我的订单页
		}

		$items = Db::table('products')
			->alias('p')
			->join('orderdetails od', 'p.pId = od.pId')
			->join('orders o', 'od.orderId = o.orderId')
			->where('o.orderId', $orderId)
			->where('o.uName', $uname) // 确保只查询该用户的订单
			->field('p.pId, p.pName, p.pImg, p.pPrice, od.number, od.totalPrice, o.uName')
			->select();
		
		$this->assign('items', $items); // 为前台模板变量绑定值

		$sumPrice = 0;
		foreach ($items as $item) { // 计算总价
			$sumPrice += $item['totalPrice'];
		}
		$this->assign('sumPrice', $sumPrice); // 为前台模板变量绑定值

		// 生成评分数组
		$ratings = range(5, 1);
		$this->assign('ratings', $ratings);

		// 传递 $orderId 变量到视图中
		$this->assign('orderId', $orderId);

		return view();
	}




	public function productComment($id, $orderId)
{
    $uname = Session::get("username"); // 取已登录的用户名
    if (empty($uname)) {
        $this->error("请先登录", "login"); // 未登录，跳转到登录页
    }

    // 检查订单状态
    $order = Db::name('orders')->where('orderId', $orderId)->where('uName', $uname)->find();
    if (empty($order)) {
        $this->error("订单不存在或无权限查看此订单");
    }
 	// 只有订单状态为“待评价”或“交易完成”时才能进行评价
    if ($order['status'] !== '待评价' && $order['status'] !== '交易完成') {
        $this->error("只有在确认收货之后才能进行商品评价");
    }

    if (request()->isPost()) {
        // 检查是否已经评论过
        $existingComment = Db::name('comments')
            ->where('productId', input('post.productId'))
            ->where('uname', $uname)
            ->where('orderId', $orderId)
            ->find();

        if ($existingComment) {
            $this->error("您已经对该订单中的该商品进行了评论");
        }

        $data = [
            'productId' => input('post.productId'),
            'uname' => $uname,
            'message' => input('post.message'),
            'rating' => input('post.rating'),
            'orderId' => $orderId, // 添加订单ID
            'created_at' => date('Y-m-d H:i:s'),
        ];
        Db::name('comments')->insert($data);

        $this->success("评论提交成功", url('orders/orderDetail', ['id' => $orderId])); // 跳转到商品详情页
    }

    $items = Db::table('products')
        ->where('pId', $id)
        ->field('pId, pName, pImg, pPrice')
        ->select();
    $this->assign('items', $items); // 为前台模板变量绑定值
    
    // 生成评分数组
    $ratings = range(5, 1);
    $this->assign('ratings', $ratings);

    // 传递 $id 和 $orderId 变量到视图中
    $this->assign('id', $id);
    $this->assign('orderId', $orderId);

    return view();
}


	
	
	

		



}
