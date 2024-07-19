<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class OrderComments extends Controller
{
    // 查看所有订单评价
    public function index()
    {
        // 获取所有评论并分页
        $comments = Db::name('ordercomments')->paginate(10);

        // 获取所有相关订单详情
        $orderDetails = Db::name('orderdetails')
            ->alias('od')
            ->join('products p', 'od.pId = p.pId')
            ->field('od.*, p.pName')
            ->select();

        $this->assign('comments', $comments);
        $this->assign('orderDetails', $orderDetails);
        return $this->fetch();
    }

    // 删除订单评价
    public function delete($id)
    {
        $result = Db::name('ordercomments')->delete($id);
        if ($result) {
            return $this->success('评价删除成功', 'admin/ordercomments/index');
        } else {
            return $this->error('评价删除失败');
        }
    }
    // 查看所有商品评价
    public function productComment()
    {
        // 获取所有评论并分页
        $comments = Db::name('comments')->paginate(10);

        // 获取所有相关的商品信息
        $products = Db::name('products')->select();

        $this->assign('comments', $comments);
        $this->assign('products', $products);
        return $this->fetch();
    }
     // 回复商品评价
     public function reply($id)
    {
        // 获取评价信息
        $comment = Db::name('comments')->find($id);

        if (!$comment) {
            $this->error('评价不存在');
        }

        // 获取商品信息
        $product = Db::name('products')->where('pId', $comment['productId'])->find();

        $this->assign('comment', $comment);
        $this->assign('product', $product);
        return $this->fetch(); // 加载回复页面模板
    }
 
     // 处理回复提交
     public function saveReply()
     {
         $data = $this->request->post();
 
         // 假设你已经有管理员登录信息，并且存储在 session 中
         $adminId = session('admin_id');
 
         if (!$adminId) {
             return json(['code' => 401, 'msg' => '管理员未登录']);
         }
 
         // 数据验证
         $validate = $this->validate($data, [
             'comment_id'    => 'require|integer',
             'reply_content' => 'require'
         ]);
 
         if ($validate !== true) {
             return json(['code' => 400, 'msg' => $validate]);
         }
 
         // 保存回复数据
         $result = Db::name('comment_replies')->insert([
             'comment_id'    => $data['comment_id'],
             'admin_id'      => $adminId,
             'reply_content' => $data['reply_content'],
             'created_at'    => date('Y-m-d H:i:s')
         ]);
 
         if ($result) {
            return $this->success('回复成功', 'admin/ordercomments/productComment');
         } else {
            return $this->error('回复失败');
         }
     }

}