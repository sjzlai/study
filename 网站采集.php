<?php

// 设置脚本不限制时间一直执行到结束
set_time_limit(0);

/*

create database cj_movie default charset utf8;
use cj_movie;
create table cj_movie
(
	id mediumint unsigned not null auto_increment,
	title varchar(50) not null comment '电影名称',
	logo varchar(150) not null comment '图片',
	link varchar(150) not null comment '链接',
	primary key (id)
)engine=InnoDB comment '电影基本信息';

*/

class Movie
{
	private $init;

	public function __construct()
	{
		// 创建一个下载用的对象
		$this->init = curl_init();
		// 返回字符串不要直接输出
		curl_setopt($this->init, CURLOPT_RETURNTRANSFER, TRUE);
	}

	// 根据URL地址下载一个页面
	public function get($url)
	{
		curl_setopt($this->init, CURLOPT_URL, $url);
		return curl_exec($this->init);
	}

	// 从一个字符串中截取出一小段
	public function getSection($str, $start, $end)
	{
		// 开始的位置
		$startpos = strpos($str, $start);
		// 结束的位置
		$endpos = strpos($str, $end);
		// 开始标记的长度
		$startlen = strlen($start);
		// 内容的长度
		$conlen = $endpos - $startpos - $startlen;
		// 截取
		return substr($str, $startpos + $startlen, $conlen);
	}	

	// 开始采集
	public function start()
	{
		$pdo = new PDO('mysql:host=localhost;dbname=cj_movie', 'root', '');
		$pdo->exec('SET NAMES utf8');
		$stmt = $pdo->prepare('INSERT INTO cj_movie VALUES(null,?,?,?)');

		// 要采集的分类
		$arr = ['aiqing','dongzuo','xiju'];

		// 计数
		$totalnum = 0;

		foreach($arr as $v0)
		{
			// 循环页数
			for($i=1; $i<=100; $i++)
			{
				// 先下载页面的HTML
				$html = $this->get("http://dianying.2345.com/list/$v0-------$i.html");
				// 截取出包含电脑的那一段字符串
				$html = $this->getSection($html, 'pic180_240', 'v_page');
				// 通过正则匹配出每个li标签
				$li_re = '/<li.+>.+<\/li>/Us';
				// 把匹配到的LI保存到$li中
				preg_match_all($li_re, $html, $li);

				$img_re = '/<img.+data-src="(.+)".+alt="(.+)">/Us';
				$a_re = '/<a.+href="(.+)".+>.+<\/a>/Us';

				// 循环每个电影下载
				foreach($li[0] as $v)
				{
					// 匹配图片和标题
					preg_match($img_re, $v, $img);
					$img[2] = mb_convert_encoding($img[2], 'utf-8', 'gb2312');
					// 匹配详情页的链接
					preg_match($a_re, $v, $link);
					$stmt->bindValue(1, $img[2]);
					$stmt->bindValue(2, $img[1]);
					$stmt->bindValue(3, $link[1]);
					$stmt->execute();
					// 计数
					if(++$totalnum % 10 == 0)
					{
						echo $totalnum . '已下载完成<br>';
						ob_flush();
						flush();
						// 休息一下，把内存中的数据导入到数据库中
						sleep(2);
					}
				}
			}
		}
		echo 'ok!';
	}
}

$a = new Movie;
$str = $a->start();