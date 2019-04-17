<?php
class Pager {

    private $params = [];
    private $pager = [];
    private $id = '';
    private $base_url = '';


    public  function __construct($base_url= '',$params = [],$pager = [],$id ='pagination')
    {
        $this->params = $params;
        $this->pager = $pager;
        $this->id = $id;
        $this->base_url = $base_url;
    }

    public function url($params)
    {
        return $this->base_url.'?'.http_build_query($params);
    }

    public function render($return = false)
    {
        $attrs = [
            'length' => 9,
            'slider' => 2,
            'prev_label' => '&lt;前页',
            'next_label' => '后页&gt;',
        ];

        $pager = $this->pager;
        $id = $this->id;

        if(! $pager['record_count'] || ! ($pager['page_count'] > 1)) {
            return '';
        }

        $params = array_merge($_GET,$this->params);

        $s = "<ul class=\"{$id}\">\n";
//        $s .= "<div class=\"stat\">共{$pager['record_count']}条数据</div>\n";

        if ($pager['current'] == $pager['first']) {
            $s .= "<li class=\"disabled\"><a>{$attrs['prev_label']}</a></li>\n";
        } else {
            $params['page'] = $pager['prev'];
            $url = $this->url($params);
            $s .= "<li><a href=\"{$url}\">{$attrs['prev_label']}</a></li>\n";
        }

        $current = $pager['current'];

        $mid = intval($attrs['length'] / 2);
        if ($current < $pager['first']) {
            $current = $pager['first'];
        }

        if ($current > $pager['last']) {
            $current = $pager['last'];
        }

        $begin = $current - $mid;
        if ($begin < $pager['first']) {
            $begin = $pager['first'];
        }

        $end = $begin + $attrs['length'] - 1;
        if ($end >= $pager['last']) {
            $end = $pager['last'];
            $begin = $end - $attrs['length'] + 1;
            if ($begin < $pager['first']) {
                $begin = $pager['first'];
            }
        }

        if ($begin > $pager['first']) {
            for ($i = $pager['first']; $i < $pager['first'] + $attrs['slider'] && $i < $begin; $i++) {
                $params['page'] = $i;
                $url = $this->url($params);
                $s .= "<li><a href=\"{$url}\">{$i}</a></li>\n";
            }

            if ($i < $begin) {
                $s .= "<li><span>...</span></li>";
            }
        }

        for ($i = $begin; $i <= $end; $i++) {
            $params['page'] = $i;
            $url = $this->url($params);
            if ($i == $pager['current']) {
                $s .= "<li class=\"active\" ><a href=\"{$url}\">{$i}</a></li>\n";
            } else {
                $s .= "<li><a href=\"{$url}\">{$i}</a></li>\n";
            }
        }

        if ($pager['last'] - $end > $attrs['slider']) {
            $s .= "<li><span>...</span></li>";
            $end = $pager['last'] - $attrs['slider'];
        }

        for ($i = $end + 1; $i <= $pager['last']; $i++) {
            $params['page'] = $i;
            $url = $this->url($params);
            $s .= "<li><a href=\"{$url}\">{$i}</a></li>\n";
        }

        if ($pager['current'] == $pager['last']) {
            $s .= "<li class=\"disabled\" ><a>{$attrs['next_label']}</a>\n";
        } else {
            $params['page'] = $pager['next'];
            $url = $this->url($params);
            $s .= "<li><a  href=\"{$url}\">{$attrs['next_label']}</a></li>\n";
        }

        $s .= "</ul>\n";
        if($return) {
            return $s;
        }
        echo $s;
    }

    public static function getPagination($totalnum = 0, $page = 1, $page_size = ADM_PAGE_SIZE)
    {
        $pagenum = ceil($totalnum / $page_size);
        $pagination = [
            "record_count" => $totalnum,
            "page_count" => $pagenum,
            "first" => 1,
            "last" => $pagenum,
            "next" => min($pagenum, $page + 1),
            "prev" => max(1, $page - 1),
            "current" => $page,
            "page_size" => $page_size,
            "page_base" => 1,
        ];
        return $pagination;
    }
}