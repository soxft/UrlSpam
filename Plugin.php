<?php
/**
 * 评论网址过滤
 *
 * @package UrlSpam
 * @author xcsoft
 * @version 1.1
 * @link https://blog.xsot.cn
 *
 */

class UrlSpam_Plugin implements Typecho_Plugin_Interface
{
  public static function activate() {
    Typecho_Plugin::factory('Widget_Feedback')->comment = array('UrlSpam_Plugin', 'filter');
    return _t('UrlSpam启动成功,请设置详细信息');
  }
  /* 禁用插件方法 */
  public static function deactivate() {}

  /* 插件配置方法 */
  public static function config(Typecho_Widget_Helper_Form $form) {
    echo "<p>欢迎使用本插件,本插件主要是为了有些人随意在博客下方评论某写无用链接而制作的,调用的腾讯网址检测api</p>";
    echo '<p>最后两行为垃圾评论TG推送设置,用于收到垃圾评论时推送到telegram bot,如不了解请留空</p>';
    echo '<p>Designed by <a href="https://xsot.cn" target="_blank">xcosft</a> | <a href="https://github.com/soxft/urlspam" target="_blank">Github</a>求star哈哈哈';
    echo '<hr />';
    $api = new Typecho_Widget_Helper_Form_Element_Text('api', NULL,'https://api.xsot.cn/urlsafe/?url=','网址安全检测API:','填写一个网址安全检测api,默认为我自建的,调用了腾讯网址检测api:(https://api.xsot.cn/urlsafe/?url=)');
    $form->addInput($api);

    $url = new Typecho_Widget_Helper_Form_Element_Radio('url', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
      _t('危险的评论网址操作'), "如果评论者网址危险操作");
    $form->addInput($url);

    $http = new Typecho_Widget_Helper_Form_Element_Radio('http', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
      _t('评论内容包含网址操作'), "如果评论内容中包含网址操作");
    $form->addInput($http);
    
    $tg_token = new Typecho_Widget_Helper_Form_Element_Text('tg_token', NULL,'','token','填写机器人的token');
    $form->addInput($tg_token);

    $tg_id = new Typecho_Widget_Helper_Form_Element_Text('tg_id', NULL,'','填写你的id','可以添加https://t.me/@getidsbot发送/about 获取你的id');
    $form->addInput($tg_id);
  }

  /* 个人用户的配置方法 */
  public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

  /* 插件实现方法 */
  public static function filter($comment, $post) {
    $options = Typecho_Widget::widget('Widget_Options');
    $filter_set = $options->plugin('UrlSpam');
    $opt = "none";
    $content = "";

    //屏蔽网址处理
    if (!empty($comment['url'])) {
      $api = $filter_set->api;
      if (!empty($filter_set->tg_token)) {
        $tg_token = $filter_set->tg_token;
        $tg_id = $filter_set->tg_id;
      }
      $comment_url = $comment['url'];
      $comment_text = $comment['text'];
      $data = file_get_contents($api . $comment_url);
      $data = json_decode($data,true);
      $type = $data['type'];
      if ($type == 2) {
        $content = "评论网址不安全";
        $opt = $filter_set->url;
        file_get_contents("https://api.telegram.org/bot$tg_token/sendMessage?chat_id=$tg_id&text=又有个脑残智障在你的博客上瞎评论了,评论网址是:[[$comment_url]]内容是[[$comment_text]]");
      }
    }

    if (strpos($comment['text'],"http") !== false) {
      $content = "评论中包含网址";
      $opt = $filter_set->http;
      file_get_contents("https://api.telegram.org/bot$tg_token/sendMessage?chat_id=$tg_id&text=又有个脑残智障在你的博客上瞎评论了,评论网址是:[[$comment_url]]内容是[[$comment_text]]");
    }

    //处理
    if ($opt == "abandon") {
      Typecho_Cookie::set('__typecho_remember_text', $comment['text']);
      throw new Typecho_Widget_Exception($content);
    } else if ($opt == "spam") {
      $comment['status'] = 'spam';
    } else if ($opt == "waiting") {
      $comment['status'] = 'waiting';
    }
    Typecho_Cookie::delete('__typecho_remember_text');
    return $comment;
  }
}