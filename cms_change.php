<?php
/***************************************************************************
 *
 * Copyright (c) 2015 test.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file cms_change.php
 * @author skymoons(com@test.com)
 * @date 2015/04/14 14:22:09
 * @brief
 *
 **/

define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
require_once  './config.php';
require_once  './Email.php';


class cms_change {

    //各个国家对应的cms id && index number
    private $_CountryConfig;
    private $_upres ;
    private $_res;
    private $_cookie;
    private $_filepath;
    private $_country_model;
    private $_dbConfig;
    private $_sendTo;
    private $_dbObj;
    /**
        *@function : __construct
        *
        *@param : $url 对应的api url
        *
        *@return :
     */
    public function __construct($url){
        $this->_filepath        = countryConf::$filepath;
        $this->_log('------------------Hi---------------------------');
        $this->_upres           = countryConf::$upres;
        $this->_res             = countryConf::$res;
        $this->_cookie          = countryConf::$cookie;
        $this->_dbConfig        = countryConf::$dbConfig;
        $this->_country_model   = countryConf::$country_model;
        $this->_run($url);
        $this->_log('-------------------Bye-------------------------');
    }
    /**
        *@function : _run 各个函数的实际入口，具体实现查阅各个函数
        *
        *@param : $url api地址
        *
        *@return : 无
     */
    public function _run($url){
        //URL 分类
        $param    = $this->url_encode($url);
        //从url返回的国家来选择具体的配置
        $this->_CountryConfig = countryConf::$countryconfig[$param[1]];
        $this->_sendTo        = countryConf::$sendTo[$param[1]];
        //connect mysql
        $this->conn = new mysqli($this->_dbConfig['host'],$this->_dbConfig['user'],$this->_dbConfig['passwd'],$this->_dbConfig['db'],$this->_dbConfig['port']);
        $this->conn->set_charset($this->_dbConfig['charset']);
        if($this->conn->connect_error){
            $this->_log('Mysql connect error'.PHP_EOL);
        }
        //下载cms数据
        $cms_data = $this->down_cms_data();
        if($cms_data == ''){exit;}
        //备份，万一跪了呢
        $this->_log($cms_data,1);
        //json转化成php数组
        $cms_data = json_decode($cms_data,true);
        //修改数据
        $cms_data = $this->change_cms_data($cms_data,$param,$url);
        if($cms_data == ''){exit;}
        //将php数组转化成json
        $cms_data = json_encode($cms_data);
        //上传cms数据
        $this->upload_cms_data($cms_data);
        //更新cms数据
        $this->updata_cms_data();
        $this->conn->close();
    }
    /**
        *@function : url_encode 区分两种类型：vlistData和app=xx
        *
        *@param    : $url api url
        *
        *@return   : 得到对应的国家和模块名称
        *            model①:country,tag1,tag2,st,start,rn
        *            model②:country,app,act,NULL,model,type,
     */
    public function url_encode($url){
        $this->_log('url encode start !');
        $temp   = preg_match('/api\.php/',$url);
        $info   = array();
        $result = array();
        //xx.hao123.com/xx/vlistData
        if($temp != 1){
            //拿到国家参数
            $tmp   = parse_url($url);
            $param = explode('.',$tmp['host']);
            $result[0] = 1;
            $result[1] = $param[0];
            $result[2] = 'vlistData';
            //需要的参数
            $model = $this->_country_model[$result[0]][$result[1]][1];
            if($model == 0){
                $result[3]=null;
                $this->_log('url encode end!model xx.hao123.com/xx/vlistData!');
                return $result;
            }
            //对应的tag(tag1,tag2,st,start,rn)
            $param = explode('&',$tmp['query']);
            foreach($param as $key => $action){
                $info[$key] = explode('=',$action);
                if($info[$key][0] == $model){
                    $result[3] = $info[$key][1];
                }
            }
            $this->_log('url encode end!model xx.hao123.com/xx/vlistData!');
            return $result;
        }
        //api.xx.hao123.com/api.php?app=xx
        $param     = explode('&',$url);
        $result[0] = 0;
        foreach($param as $key => $action){
            $info[$key] = explode('=',$action);
            if($info[$key][0] == 'country'){
                $result[1] = $info[$key][1];
            }
            if($key == 0){
                $result[2] = $info[$key][1];
            }
        }
        $model  = $this->_country_model[$result[1]][$result[2]][1];
        if($model == 0){
            $result[3]=null;
            $this->_log('url encode end! model api.xx.hao123.com/api.php?app=xx!');
            return $result;
        }
        foreach($info as $key => $value){
            if($value[0] == $model){
                $result[3] = $value[1];
            }
        }
        $this->_log('url encode end! model api.xx.hao123.com/api.php?app=xx!');
        return $result;
    }
    /**
        *@function : _log write log
        *
        *@param : $error_info 错误信息
        *
        *@param : $model 默认无参数,有值时为备份文件
        *
        *@return : 无返回信息
     */
    public function _log($error_info,$model=0){
        $time = date('Y-m-d H:i:s');
        $fp = fopen($this->_filepath.'/log.txt','a');
        if($model ==1){
        $fp = fopen($this->_filepath.'/backup.txt','a');
        }
        fwrite($fp,$time.'  '.$error_info."\n");
        fclose($fp);
    }
    /**
        *@function : del_cms_data 递归函数，删除对应元素
        *
        *@param : $cms_data
        *@param : $path    路径 eg:/body/pray/1
        *@param : $param   url_encode 传入参数
        *@param : $c_m     country和model的对应关系
        *
        *@return :  用&直接对cms_data数组操作，无返回
     */
    public function del_cms_data(&$cms_data,$path,$param,$c_m,$url){
        $model = $path[0];
        if(is_array($path) && 1 < count($path)){
            $path = array_pop($path);
            $this->del_cms_data($cms_data[$model],$path,$param,$c_m,$url);
            return;
        }
        $path = $model;
        if(!isset($cms_data[$path])){
            $this->_log('找不到这个模块');
            return ;
        }
        $time = strftime('%Y-%m-%d %H:%M:%S');
        $filepath =strftime('%Y%m%d%H%M%S');
        $sql  = "insert into ".$this->_dbConfig['table']." (`time`, `country`, `model`, `list`, `api add`, `status`, `filepath`) values ('$time','$param[1]',";
        $fp   = fopen($this->_filepath.'/'.$filepath.'.txt','a');
        if($param[3] ==null){
            $sql_tmp ="'$c_m[0]',0,'$url',1,'$filepath')";
            fwrite($fp,json_encode($cms_data[$path]));
            fclose($fp);
            unset($cms_data[$path]);
            $tmp = $param[1].'/'.$c_m[0].'模块已删除';
            $this->_log($tmp);
            $this->sendMail($this->_sendTo,'CMS 自动下线通知','Hi,'."\n$tmp");
            $this->conn->query($sql.$sql_tmp);
            if($this->conn->error != ''){
                $this->_log($this->conn->error.PHP_EOL);
            }

            return ;
        }
        foreach($cms_data[$path] as $key => $value){
            foreach($value as $k =>$v){
                if($k == $c_m[1]&&$v == $param[3]){
                    $tmp = $param[1].'/'.$c_m[0].'模块已删除';
                    $sql_tmp ="'$c_m[0]','$k','$url',1,'$filepath')";
                    $this->_log($tmp);
                    $this->sendMail($this->_sendTo,'CMS 自动下线通知','Hi,'."\n$tmp");
                    fwrite($fp,json_encode($cms_data[$key][$k]));
                    fclose($fp);
                    unset($cms_data[$key][$k]);
                    $this->conn->query($sql.$sql_tmp);
                    if($this->conn->error != ''){
                        $this->_log($this->conn->error.PHP_EOL);
                    }
                    return ;
                }
            }
        }
    }
     /**  @function CMS file change
     *  @type     json 格式的cms data
     *  @param    $cms_data 原始的cms data
     *  @param    $param url 格式化之后的结果
     *  @param    $type 预留接口
     *  @return   处理之后的cms data
     *
     *  */
    public function change_cms_data($cms_data,$param,$url){
        //xx.hao123.com/xx/vlistData
        $c_m =countryConf::$country_model[$param[1]][$param[2]];
        if($c_m == null){
            $this->_log('找不到对应模块配置');
            return '';
        }
        $path = explode('/',$c_m[0]);
        $this->del_cms_data($cms_data['body'],$path,$param,$c_m,$url);
        return $cms_data;
    }
    /**
    *   @function 上传cms_index 文件
    *   @type     urlencode 之后的cms数据
    *   @param    $cms_data 处理之后的json串
    *   @return   执行状态，非NULL表示错误
    *
    * */
    public function upload_cms_data($cms_data){
        $this->_log('upload cms data strat!');
        $cms_data =Urlencode($cms_data);
        $cms_data ='id='.$this->_CountryConfig[0].'&belong_tag=&belong_id='.$this->_CountryConfig[1].'&action=update&root='.$cms_data;
        $result = $this->curl_post($this->_res,$cms_data);
        if($result == ''){
            $this->_log('upload cms data failed ! return :'.$result);
        }
        $this->_log('upload cms data end!');
    }
   /**
    *   @function 下载cms_index 数据
    *   @type     数据格式为json格式
    *   @param    $country 对应的国家
    *   @return   返回json格式的cms数据
    *
    * */
    public function down_cms_data(){
        $this->_log('Down Cms data start!');
        $res = $this->_res .'?action=edit&id='. $this->_CountryConfig[0] .'&belong_tag=&belong_id=' . $this->_CountryConfig[1].'&action=check';
        $cms_data = $this->curl_get($res);
        if(strlen($cms_data)=='620'){
            $err = 'Cookie is overdue!';
            $this->_log($err);
            exit;
        }elseif($cms_data ==''){
            $err ='Down cms data failed!';
            $this->_log($err);
            exit;
        }else{
            $this->_log('Down cms data end !');
            return $cms_data;
        }
        return '';
    }
    /**
     * @param 更新cms文件
     *
     * @return
     *
     * */
    public function updata_cms_data(){
        $this->_log('update cms date start!');
        $result = $this->curl_get($this->_upres.$this->_CountryConfig[1]);
        $result = json_decode($result,true);
        $this->_log('update cms date end!');
        if(empty($json[0]['fail_reason'])){
            $this->_log('update cms date sucessed!');
        }
        else{
            $this->_log('update cms date failed!');
        }
    }
    /**
     *  @function 封装好的post请求类，可以直接调用，如果后期文件比较大，可以考虑单独写个文件
     *  @param    $res 请求的URL, $file_data 上传的文件/数据
     *  @return   执行结果，空为false
     *  */

    public function curl_post($res, $file_data){
        $ch = curl_init();
        $timeout = 10;
        curl_setopt($ch, CURLOPT_URL, $res);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_COOKIE, $this->_cookie);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
        try{
            $file_contents = curl_exec($ch);
            curl_close($ch);
            return $file_contents ;
        }catch (Exception $e){
            $this->_log('post error: '.$e->getMessage());
            return '';
        }
    }
    /**
     *  @function 封装好的get请求类
     *  @param    $res 请求URL
     *  @return   网页返回值
     *
     * */
    public function curl_get($res){
        $ch      = curl_init();
        $timeout = 10 ;
        curl_setopt($ch, CURLOPT_URL, $res);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_COOKIE, $this->_cookie);
        try{
            $file_contents = curl_exec($ch);
            curl_close($ch);
            return $file_contents ;
        }catch (Exception $e){
            $this->_log('get error : '.$e->getMessage());
            return '';
        }
    }
    public function sendMail($sendTo,$title,$msg){
        $Email = new Email();
        $config['protocol'] = 'sendmail';
        $Email->initialize($config);
        $Email->from('skymoons@test.com');
        $Email->to($sendTo);
        //$Email->bcc('mengrui@test.com');
        $Email->subject($title);
        $Email->message($msg);
        $Email->send();
    }
}
//$url = 'http://api.gid.hao123.com/api.php?app=pray&act=contents&country=id&city=Jakarta&jsonp=ghao123_c6ae6d3fc94af556&_=1426244982078';
new cms_change($argv[1]);
//new cms_change($url);
/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
