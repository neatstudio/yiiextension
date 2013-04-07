#**Extension for Yii framework**

##欢迎关注
@weibo : [@neatstudio](http://weibo.com/neatstudio)   
@website: [neatstudio.com](http://neatstudio.com)

##upyun
	@link http://upyun.com
	@description
		upyun API for yii
	@author gouki

##php飞信类
    @desctipion
        PHP的飞信类，你懂的。
    @useage
        $fetion = new PHPFetion('用户名','密码');
        $fetion->send('对方手机','信息');
        会自动识别自己还是对方。（非好友不能发哦）

##自动刷新ddns
    @description
        其实这个程序是扔在命令行下的，最好是crontab。
        因为远程获取IP地址比较耗时间
    @useage
        在使用前最好chmod +x Dnspod.php
        注意第一行#!/usr/bin/env php，当然你也可以去掉这一条，直接使用/xxx/xxx/php Dnspod.php
        crontab -e 后加入一条：
        */10 * * * * /xxx/xxx/Dnspod.php

