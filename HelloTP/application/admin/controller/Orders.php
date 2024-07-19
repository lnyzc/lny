<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class Orders extends Controller
{
    // 查看所有订单
    public function index()
    {
        $orders = Db::name('orders')->select();
        $this->assign('orders', $orders);
        return $this->fetch('index');
    }

    // 查看待付款订单
    public function orders0()
    {
        $orders = Db::name('orders')->where('status', '待付款')->select();
        $this->assign('orders', $orders);
        return $this->fetch('index');
    }

    // 查看待发货订单
    public function orders1()
    {
        $orders = Db::name('orders')->where('status', '待发货')->select();
        $this->assign('orders', $orders);
        return $this->fetch('index');
    }

    // 查看待收货订单
    public function orders2()
    {
        $orders = Db::name('orders')->where('status', '待收货')->select();
        $this->assign('orders', $orders);
        return $this->fetch('index');
    }
    public function detail($orderId)
    {
        $order = Db::name('orders')->where('orderId', $orderId)->find();
        if (!$order) {
            return $this->error('订单不存在');
        }
        
        $orderDetails = Db::name('orderdetails')
            ->alias('od')
            ->join('products p', 'od.pId = p.pId')
            ->field('od.*, p.pName, (od.number * od.totalPrice) as total')
            ->where('orderId', $orderId)
            ->select();

        $this->assign('order', $order);
        $this->assign('orderDetails', $orderDetails);
        return $this->fetch('detail');
    }

    // 更新订单状态
    public function updateStatus()
    {
        $statusUpdates = input('post.status/a');
        foreach ($statusUpdates as $orderId => $status) {
            Db::name('orders')->where('orderId', $orderId)->update(['status' => $status]);
        }
        return $this->success('订单状态更新成功', 'admin/orders/index');
    }

    // 删除订单
    public function deleteOrder($orderId)
    {
        Db::name('orders')->where('orderId', $orderId)->delete();
        Db::name('orderDetails')->where('orderId', $orderId)->delete();
        return $this->success('订单删除成功', 'admin/orders/index');
    }

    // 发货功能
    public function shipOrder($orderId)
    {
        Db::name('orders')->where('orderId', $orderId)->update(['status' => '待收货']);
        return $this->success('订单已发货', 'admin/orders/index');
    }
}