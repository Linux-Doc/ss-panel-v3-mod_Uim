<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Models\DetectRule;
use App\Utils\{
    Telegram,
    DatatablesHelper
};
use Ozdemir\Datatables\Datatables;
use Slim\Http\{
    Request,
    Response
};

class DetectController extends AdminController
{
    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function index($request, $response, $args)
    {
        $table_config['total_column'] = array(
            'op'    => '操作',
            'id'    => 'ID',
            'name'  => '名称',
            'text'  => '介绍',
            'regex' => '正则表达式',
            'type'  => '类型'
        );
        $table_config['default_show_column'] = array();
        foreach ($table_config['total_column'] as $column => $value) {
            $table_config['default_show_column'][] = $column;
        }
        $table_config['ajax_url'] = 'detect/ajax';
        return $response->write(
            $this->view()
                ->assign('table_config', $table_config)
                ->display('admin/detect/index.tpl')
        );
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function log($request, $response, $args)
    {
        $table_config['total_column'] = array(
            'id'          => 'ID',
            'user_id'     => '用户ID',
            'user_name'   => '用户名',
            'node_id'     => '节点ID',
            'node_name'   => '节点名',
            'rule_id'     => '规则ID',
            'rule_name'   => '规则名',
            'rule_text'   => '规则描述',
            'rule_regex'  => '规则正则表达式',
            'rule_type'   => '规则类型',
            'datetime'    => '时间'
        );
        $table_config['default_show_column'] = array();
        foreach ($table_config['total_column'] as $column => $value) {
            $table_config['default_show_column'][] = $column;
        }
        $table_config['ajax_url'] = 'log/ajax';
        return $response->write(
            $this->view()
                ->assign('table_config', $table_config)
                ->display('admin/detect/log.tpl')
        );
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function create($request, $response, $args)
    {
        return $response->write(
            $this->view()
                ->display('admin/detect/add.tpl')
        );
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function add($request, $response, $args)
    {
        $rule = new DetectRule();
        $rule->name = $request->getParam('name');
        $rule->text = $request->getParam('text');
        $rule->regex = $request->getParam('regex');
        $rule->type = $request->getParam('type');

        if (!$rule->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '添加失败'
            ]);
        }

        Telegram::SendMarkdown('有新的审计规则：' . $rule->name);
        return $response->withJson([
            'ret' => 1,
            'msg' => '添加成功'
        ]);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function edit($request, $response, $args)
    {
        $id = $args['id'];
        $rule = DetectRule::find($id);
        return $response->write(
            $this->view()
                ->assign('rule', $rule)
                ->display('admin/detect/edit.tpl')
        );
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function update($request, $response, $args)
    {
        $id = $args['id'];
        $rule = DetectRule::find($id);

        $rule->name = $request->getParam('name');
        $rule->text = $request->getParam('text');
        $rule->regex = $request->getParam('regex');
        $rule->type = $request->getParam('type');

        if (!$rule->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '修改失败'
            ]);
        }
        Telegram::SendMarkdown('规则更新：' . PHP_EOL . $request->getParam('name'));
        return $response->withJson([
            'ret' => 1,
            'msg' => '修改成功'
        ]);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function delete($request, $response, $args)
    {
        $id = $request->getParam('id');
        $rule = DetectRule::find($id);
        if (!$rule->delete()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '删除失败'
            ]);
        }
        return $response->withJson([
            'ret' => 1,
            'msg' => '删除成功'
        ]);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function ajax_rule($request, $response, $args)
    {
        $datatables = new Datatables(new DatatablesHelper());
        $datatables->query('Select id as op,id,name,text,regex,type from detect_list');
        $datatables->edit('op', static function ($data) {
            return '<a class="btn btn-brand" href="/admin/detect/' . $data['id'] . '/edit">编辑</a>
                    <a class="btn btn-brand-accent" id="delete" value="' . $data['id'] . '" href="javascript:void(0);" onClick="delete_modal_show(\'' . $data['id'] . '\')">删除</a>';
        });
        $datatables->edit('type', static function ($data) {
            return $data['type'] == 1 ? '数据包明文匹配' : '数据包十六进制匹配';
        });
        return $response->write(
            $datatables->generate()
        );
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function ajax_log($request, $response, $args)
    {
        $datatables = new Datatables(new DatatablesHelper());
        $datatables->query('Select detect_log.id,user_id,user.user_name,node_id,node.name as node_name,list.id as rule_id,list.name as rule_name,list.text as rule_text,list.regex as rule_regex,list.type as rule_type,detect_log.datetime from detect_log,user,ss_node as node,detect_list as list where user.id=detect_log.user_id and node.id = detect_log.node_id and list.id = detect_log.list_id');
        $datatables->edit('rule_type', static function ($data) {
            return $data['rule_type'] == 1 ? '数据包明文匹配' : '数据包十六进制匹配';
        });
        $datatables->edit('datetime', static function ($data) {
            return date('Y-m-d H:i:s', $data['datetime']);
        });
        return $response->write(
            $datatables->generate()
        );
    }
}
