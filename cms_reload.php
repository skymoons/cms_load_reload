<?php
/***************************************************************************
 *
 * Copyright (c) 2015 test.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file reload.php
 * @author skymoons(com@test.com)
 * @date 2015/04/14 14:22:09
 * @brief
 *
 **/

define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
require_once  './config.php';
require_once  './Email.php';


class cms_reload {

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
        *
        *@return :null
     */
    public function __construct(){
        $this->_filepath        = countryConf::$filepath;
        $this->_log('------------------Hi---------------------------');
        $this->_upres           = countryConf::$upres;
        $this->_res             = countryConf::$res;
        $this->_cookie          = countryConf::$cookie;
        $this->_dbConfig        = countryConf::$dbConfig;
        $this->_country_model   = countryConf::$country_model;
        $this->_run();
        $this->_log('-------------------Bye-------------------------');
    }
    /**
        *@function : _run 从数据库中查询状态为1的数据
        *
        *
        *@return : 无
     */
    public function _run(){
        //connect mysql
        $this->conn = new mysqli($this->_dbConfig['host'],$this->_dbConfig['user'],$this->_dbConfig['passwd'],$this->_dbConfig['db'],$this->_dbConfig['port']);
        $this->conn->set_charset($this->_dbConfig['charset']);
        if($this->conn->connect_error){
            $this->_log('Mysql connect error'.PHP_EOL);
        }
        $arrData=$this->selectData();
        foreach($arrData as $key => $value){
            $this->start($value);
        }
        $this->conn->close();
    }
    /**
        *@function : start 具体逻辑入口函数
        *
        *@param : $arrDataStaus 数据库单条记录
        *@数据库:@time 入库时间   @country国家  @model模块  @list模块顺序
        *        @api add：apiUrl @status: 状态(0已生效/1已下线) @filepath:文件存储路径
        *
        *@return :
     */
    public function start($arrData){
        $this->_CountryConfig = countryConf::$countryconfig[$arrData['country']];
        $this->_sendTo        = countryConf::$sendTo[$arrData['country']];
        $strApiData = $this->Curl_get($arrData['api add']);
        if(strlen($strApiData)< 40){
            return;
        }
        //下载cms数据
        $arrCmsData = $this->down_cms_data();
        if($arrCmsData == ''){exit;}
        //备份，万一跪了呢
        $this->_log($arrCmsData,1);
        //json转化成php数组
        $arrCmsData = json_decode($arrCmsData,true);
        //修改数据
        $arrCmsData=$this->recoverData($arrData,$arrCmsData);
        //exit;
        if($arrCmsData == ''){exit;}
        //将php数组转化成json
        $arrCmsData= json_encode($arrCmsData);
        //上传cms数据
        $this->upload_cms_data($arrCmsData);
        //更新cms数据
        $this->updata_cms_data();

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
        $fp = fopen($this->_filepath.'/reloadlog.txt','a');
        if($model ==1){
        $fp = fopen($this->_filepath.'/backup.txt','a');
        }
        fwrite($fp,$time.'  '.$error_info."\n");
        fclose($fp);
    }
    /**
        *@function : selectData 从数据库查询已经下线的数据
        *
        *@return : arr 所有下线的数据
     */
    public function selectData(){
        $sql = "select `country`,`model`,`list`,`api add`,`status`,`filepath` from ".$this->_dbConfig['table']." where status=1";
        $res = $this->conn->query($sql);
        if($res){
            $arr = array();
            while(($row = $res->fetch_assoc()) != null ){
                $arr[] = $row;
            }
        }
        return $arr;
    }
    /**
        *@function : readData 从文件中读取之前下线的cms内容
        *
        *@param : $filepath 文件名
        *
        *@return : php格式的数组
     */
    public function readData($filepath){
            $fp =fopen($this->_filepath.'/'.$filepath.'.txt','r');
            $data =fgets($fp);
            if($data ==''){
                $this->_log('RecoverData failed, cms data is null!');
            }
            $data = json_decode($data,true);
            $this->_log('Read cms data sucessed!');
            return $data;
    }
    /**
        *@function : mergeData 数据拼接
        *
        *@param : $arrCmsData 原始线上数据
        *@param : $reData     下线的数据
        *@param : $path       路径
        *@param : $list       列表
        *
        *@return :    null
     */
    public function mergeData(&$arrCmsData,$reData,$path,$list){
            $model = $path[0];
            if(1 < count($path)){
                $path = array_pop($path);
                $this->mergeData($arrCmsData[$model],$path,$list);
                return ;
            }
            $reData[$model] = $reData;
            $this->_log("mergeData sucessed!");
            if(0 == $list){
                $arrCmsData = array_merge($arrCmsData,$reData);
                return ;
            }
            $arrCmsData[$model] = array_merge($arrCmsData[$model],$reData);
            return ;
    }
    /**
        *@function : recoverData  恢复数据入口函数
        *
        *@param : $arrData        需要上线的数据
        *@param : $arrCmsData     线上数据
        *
        *@return : 添加上线内容的cms数组
     */
    public function recoverData($arrData,$arrCmsData){
        $this->_log('start recoverData!');
        $reData   = $this->readData($arrData['filepath']);
        $path   = explode('/',$arrData['model']);
        $this->mergeData($arrCmsData['body'],$reData,$path,$arrData['list']);
        $sql = "update ".$this->_dbConfig['table']." set `status`=0 where filepath=".$arrData['filepath'];
        $res = $this->conn->query($sql);
        $this->_log('recoverData sucessed!');
        return $arrCmsData;
    }
    /*
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
   /*
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
    /*
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
    /*
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
    /*
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
new cms_reload('dasfdsafdsafas');
