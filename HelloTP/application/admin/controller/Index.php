<?php
namespace app\admin\controller;

use think\Controller;
use think\Request;
use think\Session;
use think\Db;
use app\common\model\Admin;

class Index extends Controller
{
    public function index()
    {
        return view();
    }

    public function login()
    {
        return view();
    }

    public function loginCheck($adminName='',$adminPwd='')	
	{	
		$code=input('yzm');
        $captcha = new \think\captcha\Captcha();
        $result=$captcha->check($code);
        if($result===false){
            echo '验证码错误';exit;
        }
		//根据名字和密码$adminName询记录        
$admin = Admin::get(['adminName' => $adminName, 'adminPwd' => $adminName]);		
		if($admin){			
			//登录成功，保存用户名到session			
			Session::set('adminName',$adminName);			
			//重定向			 
			return $this->redirect('index');		
		}
		else
		{			
			return $this->error('登录失败');		
		}	
	}

    public function logout()
    {
        Session::delete('adminName');
        return $this->redirect('index');
    }

    public function show_yzm()
    {
        $captcha = new \think\captcha\Captcha();
        $captcha->imageW = 121;
        $captcha->imageH = 32;
        $captcha->fontSize = 14;
        $captcha->length = 4;
        $captcha->fontttf = '5.ttf';
        $captcha->expire = 30;
        $captcha->useNoise = false;
        return $captcha->entry();
    }

    // 显示增加管理员表单
    public function adminNew()
    {
        if (request()->isPost()) {
            $data = request()->post();
            $validate = new \think\Validate([
                'adminName' => 'require|max:50',
                'adminPwd' => 'require|max:100'
            ]);
    
            if (!$validate->check($data)) {
                return $this->error($validate->getError());
            }
    
            $admin = new Admin();
            $admin->adminName = $data['adminName'];
            $admin->adminPwd = md5($data['adminPwd']);
            if ($admin->save()) {
                return $this->success('管理员添加成功', 'index/adminList');
            } else {
                return $this->error('管理员添加失败');
            }
        }
        return $this->fetch('adminNew');
    }

    // 管理员信息管理（包括修改和删除）
    public function adminMaint()
    {
        $admins = Admin::all();
        $this->assign('admins', $admins);
        return $this->fetch('adminMaint');
    }

    // 修改管理员信息
    public function adminEdit($adminName)
    {
        $admin = Admin::get($adminName);
        if (!$admin) {
            return $this->error('管理员不存在');
        }
    
        if (request()->isPost()) {
            $data = request()->post();
            $admin->adminPwd = md5($data['adminPwd']);
            if ($admin->save()) {
                return $this->success('管理员信息修改成功', 'index/adminMaint');
            } else {
                return $this->error('管理员信息修改失败');
            }
        }
    
        $this->assign('admin', $admin);
        return $this->fetch('adminEdit');
    }

    // 删除管理员
    public function adminDelete($adminName)
    {
        $admin = Admin::get($adminName);
        if (!$admin) {
            return $this->error('管理员不存在');
        }

        if ($admin->delete()) {
            return $this->success('管理员删除成功', 'index/adminMaint');
        } else {
            return $this->error('管理员删除失败');
        }
    }

    // 上传图片
    public function uploadPic()
    {
        return view();
    }

    public function upload_image()
    {
        $file = request()->file('file');
        if ($file) {
            $info = $file->validate(['ext' => 'jpg,png,gif,bmp,jpeg'])->rule('uniqid')->move(ROOT_PATH . 'public/static' . DS . 'upload');
            if ($info) {
                $this->success("上传成功，保存的文件名为：" . $info->getSaveName());
            } else {
                $this->error("上传失败：" . $file->getError());
            }
        }
    }
    public function upload_lunboimage()
    {
        $file = request()->file('file');
        if ($file) {
            $info = $file->validate(['ext' => 'jpg,png,gif,bmp,jpeg'])->rule('uniqid')->move(ROOT_PATH . 'public/static' . DS . 'uploadlunbo');
            if ($info) {
                $this->success("上传成功，保存的文件名为：" . $info->getSaveName());
            } else {
                $this->error("上传失败：" . $file->getError());
            }
        }
    }

    // 删除图片
    public function deletePic()
    {
        $dir = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/static/upload";
        $file = scandir($dir);
        $this->assign('images', $file);
        $dirl = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/static/uploadlunbo";
        $filel = scandir($dirl);
        $this->assign('imagesl', $filel);
        return view();
    }
    

    public function delete_image($pic = '')
    {
        $parentPath = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/static/upload/";
        $filename = $parentPath . $pic;
        if ($pic != "" && file_exists($filename)) {
            unlink($filename);
            $this->success("删除成功");
        } else {
            $this->error("删除失败，文件不存在。");
        }
    }
    public function delete_lunboimage($pic = '')
    {
        $parentPath = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/static/lunboupload/";
        $filename = $parentPath . $pic;
        if ($pic != "" && file_exists($filename)) {
            unlink($filename);
            $this->success("删除成功");
        } else {
            $this->error("删除失败，文件不存在。");
        }
    }
}