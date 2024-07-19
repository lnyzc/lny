<?php
namespace app\user\controller;//定义以下类所在的命名空间
use think\View;//引用命名空间
use think\Controller;
use think\Session;
use think\Db;
use app\common\model\Users;
class Index extends Controller//定义一个类（控制器）,继承TP5的Controller类
{    
	public function index() {
		// 获取当前页码，默认为1
		$page = input('get.page', 1);
		$search = input('get.search', ''); // 获取搜索关键字
		$sortOrder = input('get.sortOrder', 'desc'); // 获取排序方式，默认为降序
		$sortType = input('get.sortType', 'sales'); // 获取排序类型，默认为销量
		$minPrice = input('get.minPrice', 0); // 获取最低价格，默认为0
		$maxPrice = input('get.maxPrice', 10000); // 获取最高价格，默认为10000
	
		// 每页显示的商品数量
		$pageSize = 6;
	
		// 保存搜索关键字
		if (!empty($search)) {
			// 先查找关键字是否存在
			$existingKeyword = Db::name('search_keywords')->where('keyword', $search)->find();
			if ($existingKeyword) {
				Db::execute('UPDATE search_keywords SET count = count + 1 WHERE keyword = ?', [$search]);
			} else {
				Db::name('search_keywords')->insert(['keyword' => $search, 'count' => 1]);
			}
		}
		$popularKeywords = Db::name('search_keywords')->order('count', 'desc')->limit(10)->select();
		$searchCondition = [];
		if (!empty($search)) {
			$searchCondition['pName'] = ['like', "%$search%"];
		}
		$searchCondition['pPrice'] = ['between', [$minPrice, $maxPrice]]; // 添加价格范围条件
		$sortField = ($sortType === 'time') ? 'pTime' : 'pSales';
		$categories = Db::name('class')->select();
		$categoryTree = $this->buildCategoryTree($categories);
		$categoryHtml = $this->renderCategories($categoryTree);
		$categoryId = input('get.category_id', null);
		$countQuery = Db::name('products')->where($searchCondition)->order($sortField, $sortOrder);
		if ($categoryId) {
			$categoryIds = $this->getCategoryIds($categoryId);
			$categoryIds[] = $categoryId; // 包括当前类别ID
			$countQuery->where('pClassId', 'in', $categoryIds)->where('pUp','1');
		}
		$totalProducts = $countQuery->count();
		$productsQuery = Db::name('products')->where($searchCondition)->where('pUp', '1')->order($sortField, $sortOrder);
		if ($categoryId) {
			$productsQuery->where('pClassId', 'in', $categoryIds);
		}
		$products = $productsQuery->page($page, $pageSize)->select();
		$pageCount = ceil($totalProducts / $pageSize);
		list($start, $end) = $this->calculatePageRange($page, $pageCount);
		$pages = range($start, $end);
		$recommendedProducts = Db::name('products')
			->where('pUp', '1')
			->order('pSales', 'desc')
			->limit(9)
			->select();

		// 读取轮播图文件
		$dir = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/static/uploadlunbo";
		$files = scandir($dir);
		$carouselImages = [];
		foreach ($files as $file) {
			if ($file != '.' && $file != '..') {
				// 假设图片名格式为 "商品ID_图片名.jpg"
				$parts = explode('_', $file);
				$productId = $parts[0];
				$carouselImages[] = [
					'url' => '/HelloTP/public/static/uploadlunbo/' . $file,
					'productId' => $productId
				];
			}
		}
	 
		 $this->assign('carouselImages', $carouselImages);

		// 将数据传递给模板
		$this->assign('products', $products);
		$this->assign('categoryHtml', $categoryHtml);
		$this->assign('currentCategoryId', $categoryId);
		$this->assign('currentPage', $page);
		$this->assign('pages', $pages);
		$this->assign('pageCount', $pageCount);
		$this->assign('search', $search); // 将搜索关键字传递给模板
		$this->assign('sortOrder', $sortOrder); // 将排序方式传递给模板
		$this->assign('sortType', $sortType); // 将排序类型传递给模板
		$this->assign('minPrice', $minPrice); // 将最低价格传递给模板
		$this->assign('maxPrice', $maxPrice); // 将最高价格传递给模板
		$this->assign('recommendedProducts', $recommendedProducts); // 将推荐商品传递给模板
		$this->assign('popularKeywords', $popularKeywords); // 将热门关键字传递给模板
	
		// 渲染模板
		return $this->fetch('index');
	}
	

	public function getCategoryIds($parentId) {
		$categories = Db::name('class')->where('parent_id', $parentId)->select();
		$ids = [];
		foreach ($categories as $category) {
			$ids[] = $category['cId'];
			$ids = array_merge($ids, $this->getCategoryIds($category['cId']));
		}
		return $ids;
	}
	
	public function buildCategoryTree($categories, $parentId = null) {
		$branch = [];
		foreach ($categories as $category) {
			if ($category['parent_id'] == $parentId) {
				$children = $this->buildCategoryTree($categories, $category['cId']);
				if ($children) {
					$category['children'] = $children;
				}
				$branch[] = $category;
			}
		}
		return $branch;
	}
	
	public function renderCategories($categories) {
		$html = '';
		foreach ($categories as $category) {
			$html .= '<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title">
								<a href="#" onclick="loadCategoryProducts(' . $category['cId'] . ')">
									' . $category['cName'] . '
								</a>
								<span class="badge pull-right" data-toggle="collapse" data-target="#category-' . $category['cId'] . '">
									<i class="fa fa-plus"></i>
								</span>
							</h4>
						</div>
						<div id="category-' . $category['cId'] . '" class="panel-collapse collapse">
							<div class="panel-body">';
			if (!empty($category['children'])) {
				$html .= '<ul>';
				foreach ($category['children'] as $child) {
					$html .= '<li>' . $this->renderCategories([$child]) . '</li>';
				}
				$html .= '</ul>';
			}
			$html .= '</div></div></div>';
		}
		return $html;
	}
	


    public function calculatePageRange($currentPage, $pageCount) {
		$pageRange = 5; // 每次显示5页
		$halfRange = floor($pageRange / 2);
		$start = max(1, $currentPage - $halfRange);
		$end = min($pageCount, $currentPage + $halfRange);
	
		if ($end - $start + 1 < $pageRange) {
			if ($start == 1) {
				$end = min($pageCount, $start + $pageRange - 1);
			} elseif ($end == $pageCount) {
				$start = max(1, $end - $pageRange + 1);
			}
		}
	
		// 确保当前页总是处于中间位置
		if ($end - $start + 1 > $pageRange) {
			if ($currentPage <= $halfRange) {
				$end = $pageRange;
			} elseif ($currentPage > $pageCount - $halfRange) {
				$start = $pageCount - $pageRange + 1;
			} else {
				$start = $currentPage - $halfRange;
				$end = $currentPage + $halfRange;
			}
		}
		return [$start-1, $end-1];
	}
	

	public function register()    
	{      //return alert_error('您好，欢迎光顾来到博客园'); 
		$categories = Db::name('class')->select();
		$categoryTree = $this->buildCategoryTree($categories);
		$categoryHtml = $this->renderCategories($categoryTree);
		$this->assign('categoryHtml', $categoryHtml);
		return view();//直接返回视图	
	}
	public function registerCheck()    
	{   $user = new Users($_POST);
		if($user->uname=="" || $user->upwd==""){
			//return alert_error('错误，用户名/密码不能为空！');
			return $this->error('错误，用户名/密码不能为空！');
		}
		if($user->upwd!=input("upwd1")){//使用input函数可以获取输入框upwd1的值
			//return alert_error('错误，两次输入密码不相同！');
			return $this->error('错误，两次输入密码不相同！');
		}		
		try{
			// 过滤post数组中的非数据表字段数据
			$user->allowField(true)->save();
		}
		catch(\Exception $e){  //捕获异常
    		$this->error($e->getMessage());
		}       //return view('index');
	    //return $this->redirect('index');
	    $this->success('注册成功！', 'index');	
	}
	public function login()    
	{ 
		$categories = Db::name('class')->select();
		$categoryTree = $this->buildCategoryTree($categories);
		$categoryHtml = $this->renderCategories($categoryTree);   
		$this->assign('categoryHtml', $categoryHtml);
		return view();//直接返回视图	
	}	
	//接收两个参数，注意参数名与html文件中的控件名相同	
	public function loginCheck($username='',$userpwd='')	
	{	$code = input('yzm');
        $captcha = new \think\captcha\Captcha();
        $result = $captcha->check($code);
        if ($result === false) {
            echo '验证码错误';
            exit;
        }
		//根据名字和密码查询记录        
		$user = Users::get(['uname' => $username, 'upwd' => $userpwd]);		
		if($user){			
			//登录成功，保存用户名到session			
			Session::set('username',$username);			
			//重定向			 
			return $this->redirect('index');		
		}
		else
		{			
			return $this->error('登录失败');		
		}	
	}
	//退出登录	
	public function logout()    
	{		
		// 删除（当前作用域）        
		Session::delete('username');		
		// 清除session（当前作用域）       
		//Session::clear();     	   
		return $this->redirect('index');	
	}
	//商品列表
	// public function product_list($cid="")
	// {   
	// 	$classes=Db::name('Class')->select();
	// 	$this->assign('clist',$classes);  //输出所有类别（布局模板左边菜单用）   
	// 	$tem=Db::name('Class')->where('cId',$cid)->find();
	// 	$this->assign('cname',$tem['cName']);//输出当前要进查看的类别的名称
	// 	//按类别号查商品且按时间降序排序，注意链式查询的使用
	// 	$list=Db::name('Products')->where('pClassId',$cid)->order('pId desc')->limit(12)->select();
	// 	$this->assign('products',$list);//输出要查看的类别的所有商品信息
	// 	return view();//直接返回视图	
	// } 
	public function product_details($id = "")
	{
		$categories = Db::name('class')->select();
		$categoryTree = $this->buildCategoryTree($categories);
		$categoryHtml = $this->renderCategories($categoryTree);

		// 获取商品详情
		$detail = Db::name('Products')->where('pId', $id)->find();
		$this->assign('product', $detail);

		// 获取销量排名前9的商品
		$recommendedProducts = Db::name('products')
			->order('pSales', 'desc')
			->limit(9)
			->select();
		$this->assign('recommendedProducts', $recommendedProducts);

		// 获取商品评论及其对应的商家回复
		$reviews = Db::name('comments')
			->alias('c')
			->join('comment_replies r', 'c.id = comment_id', 'LEFT')
			->where('c.productId', $id)
			->field('c.*, r.reply_content, r.created_at as reply_created_at')
			->paginate(5, false, [
				'query' => ['id' => $id]
			]);

		// 获取评论总数
		$totalReviews = $reviews->total();
		$this->assign('categoryHtml', $categoryHtml);
		$this->assign('totalReviews', $totalReviews);
		$this->assign('reviews', $reviews);

		return view();
	}



	public function user()
	{  
		return view();//返回视图	
	} 
	//添加到购物车
	public function addToCar($id="",$num=1)
	{   
		if ($id == "" || $num == "") {
			return json(['status' => 'error', 'message' => '参数不能为空']);
		}
		$uname = Session::get("username"); // 取已登录的用户名
		if (empty($uname)) {
			return json(['status' => 'error', 'message' => '请先登录']);
		}
	
		// 查询是否存在该商品
		$product = Db::name('Products')->where('pId', $id)->find();
		if (empty($product)) {
			return json(['status' => 'error', 'message' => "不存在商品号为：{$id} 的商品"]);
		}
	
		// 检查库存是否足够
		if ($product['pStock'] < $num) {
			return json(['status' => 'error', 'message' => '库存不足']);
		}
		if ($num <= 0) {
			return json(['status' => 'error', 'message' => '加入购物车数量必须大于0']);
		}
	
		// 查询购物车中是否已存在该商品
		$carItem = Db::name('shoppingcart')->where('uname', $uname)->where('pId', $id)->find();
		try {
			if (empty($carItem)) { // 购物车中不存在该商品
				// 构造数据
				$carItem = ['uname' => $uname, 'pId' => $id, 'number' => $num];
				Db::name('shoppingcart')->insert($carItem); // 增加记录
			} else { // 购物车中已存在该商品
				$number = $carItem['number'] + $num;
				// 检查是否超过库存
				if ($number > $product['pStock']) {
					return json(['status' => 'error', 'message' => '库存不足']);
				}
				if ($num <= 0) {
					return json(['status' => 'error', 'message' => '加入购物车数量必须大于0']);
				}
				// 修改记录
				Db::name('shoppingcart')->where('uname', $uname)->where('pid', $id)->update(['number' => $number]);
			}
		} catch (\Exception $e) { // 捕获异常
			return json(['status' => 'error', 'message' => $e->getMessage()]);
		}
		// 成功则返回提示信息
		return json(['status' => 'ok', 'message' => '已加入购物车']);
	} 
}
?>