<?php
namespace app\admin\controller;//定义以下类所在的命名空间
use think\View;//引用命名空间
use think\Controller;
use think\Session;
use think\Db;
use app\common\model\Products;
//use app\common\model\Class;
class Product extends Controller//定义一个类（控制器）,继承TP5的Controller类
{   
	public function index() { 
		$keyword = input('get.keyword');
		$search = ['query' => []];
		$search['query']['keyword'] = $keyword;
		$products = Db::name('Products')->where('pname', 'like', "%{$keyword}%")->paginate(8, false, $search);
		$this->assign('products', $products);
		$this->assign('keyword', $keyword);
		return view();
	}
	

    // 获取已上架商品
    public function listUp()
    {
        $products = Products::where('pUp', 1)->paginate(10); // 每页显示10条记录
        return $this->fetch('listup', ['products' => $products]);
    }
	    // 获取未上架商品并进行分页
	public function listDown()
	{
		$products = Products::where('pUp', 0)->paginate(10); // 每页显示10条记录
		return $this->fetch('listdown', ['products' => $products]);
	}
	public function up($id)
    {
        // 查找商品
        $product = Products::get($id);
        if ($product) {
            $product->pUp = 1; // 将商品状态设置为上架
            $product->save();
            return $this->redirect('index')->with('success', '商品上架成功！');
        } else {
            return $this->redirect('index')->with('error', '商品不存在！');
        }
    }

    // 商品下架方法
    public function down($id)
    {
        // 查找商品
        $product = Products::get($id);
        if ($product) {
            $product->pUp = 0; // 将商品状态设置为下架
            $product->save();
            return $this->redirect('index')->with('success', '商品下架成功！');
        } else {
            return $this->redirect('index')->with('error', '商品不存在！');
        }
    }
	 // 更新库存方法
	 public function updateStock()
	 {
		 $id = input('post.id');
		 $stock = input('post.stock');
 
		 if ($id && $stock !== null) {
			 $product = Products::get($id);
			 if ($product) {
				 $product->pStock = $stock;
				 $product->save();
				 return json(['status' => 'success', 'message' => '库存更新成功！']);
			 } else {
				 return json(['status' => 'error', 'message' => '商品不存在！']);
			 }
		 } else {
			 return json(['status' => 'error', 'message' => '参数错误！']);
		 }
	 }
	 public function productNew() {       
		$classes = Db::name('Class')->select(); // 列出所有数据
		$this->assign('classes', $classes);
		$dir = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/static/upload";        
		$files = scandir($dir);
		$this->assign('files', $files);
	
		return view(); // 直接返回视图    
	}
	
	//实现新增记录操作	
	public function productNewCheck() {    
		$product = new Products($_POST);
		$product->pTime = date("Y-m-d H:i:s");
		try {
			// 过滤post数组中的非数据表字段数据
			$product->allowField(true)->save();
		} catch (\Exception $e) {  // 捕获异常
			$this->error($e->getMessage());
		}
		// 添加成功，跳转到index视图，index视图稍后才建立
		$this->success('增加成功！', 'index');    
	}
	
     //详情
	public function detail($id='')    
	{       
		$detail=Db::name('Products')->where('pId',$id)->find();//只查一行
		$this->assign('detail',$detail);
		return view();//直接返回视图	
	}	
	//删除商品
	public function delete($id)
	{
		Products::destroy($id);//根据主码删除
		//Products::destroy(1);
		// 支持批量删除多个数据
		//Products::destroy('1,2,3');
		// 或者
		//Products::destroy([1,2,3]);
	//return $id;
	return $this->redirect('index');
	}
	//修改商品
	public function update($id)
    {
		$dir=dirname(dirname(dirname(dirname(__FILE__))))."/public/static/upload";		
		$file=scandir($dir);
		//var_dump($file);
		$this->assign('files',$file);
		$product = Products::get($id);
		$this->assign('product',$product);
		$classes=Db::name('Class')->select();
		$this->assign('classes',$classes);
		return view();
	}
	public function updatecheck()
    {
		$product = new Products();
		$product->pTime=date("Y-m-d H:i:s");//日期时间
		// 过滤post数组中的非数据表字段数据
		$product->allowField(true)->isUpdate(true)->save($_POST);
        //$product->allowField(true)->save($_POST,['id' => 1]);		
       //return view('index');
	   return $this->redirect('index');
	}


 } 		
