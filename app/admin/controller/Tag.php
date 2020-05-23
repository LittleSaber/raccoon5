<?php


namespace app\admin\controller;

use app\service\TagsService;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\facade\View;
use think\facade\App;
use app\model\Tags;
use think\exception\ValidateException;

class Tag extends BaseAdmin
{
    protected $tagsService;

    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->tagsService = app('tagsService');
    }

    public function index(){
        $data = $this->tagsService->getPagedAdmin();
        View::assign([
            'tags' => $data['tags'],
            'count' => $data['count']
        ]);
        return view();
    }

    public function create(){
        if (request()->isPost()) {
            $tag = new Tags();
            $tag->tag_name = input('tag_name');
            $dir = 'tags';
            if (request()->file() != null) {
                $cover = request()->file('cover');
                try {
                    validate(['image'=>'filesize:10240|fileExt:jpg,png,gif'])
                        ->check((array)$cover);
                    $savename =str_replace ( '\\', '/',
                        \think\facade\Filesystem::disk('public')->putFile($dir, $cover));
                    if (!is_null($savename)) {
                        $tag->cover_url = '/static/upload/'.$savename;
                    }
                } catch (ValidateException $e) {
                    abort(404, $e->getMessage());
                }
            }
            $result = $tag->save();
            if ($result) {
                $this->success('添加成功','index',1);
            } else {
                throw new ValidateException('添加失败');
            }
        }
        return view();
    }

    public function edit(){
        $id = input('id');
        try {
            $tag = Tags::findOrFail($id);
            if (request()->isPost()) {
                $tag->tag_name = input('tag_name');
                $dir = 'tags';
                if (request()->file() != null) {
                    $cover = request()->file('cover');
                    try {
                        validate(['image'=>'filesize:10240|fileExt:jpg,png,gif'])
                            ->check((array)$cover);
                        $savename =str_replace ( '\\', '/',
                            \think\facade\Filesystem::disk('public')->putFile($dir, $cover));
                        if (!is_null($savename)) {
                            $tag->cover_url = '/static/upload/'.$savename;
                        }
                    } catch (ValidateException $e) {
                        abort(404, $e->getMessage());
                    }
                }
                $result = $tag->save();
                if ($result) {
                    $this->success('修改成功');
                } else {
                    throw new ValidateException('修改失败');
                }
            }
            View::assign([
                'tag' => $tag,
            ]);
            return view();
        } catch (DataNotFoundException $e) {
            abort(404, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, $e->getMessage());
        }
    }

    public function delete()
    {
        $id = input('id');
        $result = Tags::destroy($id);
        if ($result) {
            return json(['err' => '0','msg' => '删除成功']);
        } else {
            return json(['err' => '1','msg' => '删除失败']);
        }
    }
}