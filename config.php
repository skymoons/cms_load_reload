<?php
/***************************************************************************
 *
 * Copyright (c) 2015 test.com, Inc. All Rights Reserved
 *
 **************************************************************************/
/**
 * @file config.php
 * @author skymoons(com@test.com)
 * @date 2015/04/24 15:14:53
 * @brief :
 *
 **/
class countryConf {
    //邮件接受人 
    public static $sendTo   =array(
         'id'   =>array('skymoons@test.com'),
         'sa'   =>array('skymoons@test.com'),
         'jp'   =>array('skymoons@test.com'),
         'ar'   =>array('skymoons@test.com'),
         'th'   =>array('skymoons@test.com'),
         'br'   =>array('skymoons@test.com'),
     );
    //数据库配置
    public static $dbConfig =array(
        'host'   =>'10.95.130.39',
        'port'   =>'3306',
        'db'     =>'qa_cms_check',
        'charset'=>'utf8',
        'user'   =>'root',
        'passwd' =>'123456',
        'table'  =>'cms_data',
    );
    //模块和cms_data 数组的对应关系，第二个值是取api哪个字段的值
    public static $country_model=array(
        'id' => array(
            'vote'      => array('voteBox',0),
            //'activity'  => array(),
            'pray'      => array('pray','city'),
            'hotpost'   => array('sidebarRetie',0),
            'news'      => array('sidebarVote/items','http://api.gid.hao123.com/api.php?app'),
            'video'     => array('pray',0),
        ),
        'sa' =>array(
            'exchrate'  => array('sidetoolbar/list/sidebarRate',0),
            'news'      => array('News/news_sort','type'),
            'vlistData' => array('bottomTabs',0),
        ),
        'jp' =>array(
           'relaxednews'=> array(),
            'shopping'  => array(),
            'star'      => array('astro',0),
        ),
        'ar' =>array(
            'pray'      => array('pray/cityList','city'),
            'exchrate'  => array('sidetoolbar/list/sidebarRate',0),
            'star'      => array('astro',0),
            'vlistData' => array('bottomTabs',0),
        ),
        'br' =>array(
            'sidebar'   => array('sidebarAppbox',),
            'series'    => array('bottomEcommerce/tabList',)
        ),
        'th' =>array(
            'star'      => array('astro',0),
        ),
    );
    //icms 中国家编号和inde的编号，后面根据编号拼接成url
    public static $countryconfig = array(
       // 'ar' =>array('35','1000061'),
        'ar' => array('76','1000103'),
        'br' => array('44','1000070'),
        'sa' => array('28','1000054'),
        'id' => array('59','1000085'),
        'jp' => array('17','1000043'),
        'th' => array('53','1000079'),
    );
    //下载数据的url前缀
    public static $res      = 'http://icms.test.com:8080/cmscript/fire/id/new_editor/';
    //上传数据的url前缀
    public static $upres    = 'http://icms.test.com:8080/cmscript/fire/id/cms_ch_config/?&action=updateChannels&ids=';
    //public static $cookie = 'cms_session=ST13995402rGflzLOP94B7eSGCWrA5uuap'ST17065191ZVkNAFqIsMIQYz3sCpjquuap;ST16137327kwYCMFmEdClAcnSEsVIYuuap
    //cookie
    public static $cookie   = 'cms_session=ST17065191ZVkNAFqIsMIQYz3sCpjquuap';
    //log日志保存路径
    public static $filepath = '/home/hao123/tool/cms/data';
}



/* vim: set expandtab ts=4 sw=4 sts=4 tw=100 */
?>
