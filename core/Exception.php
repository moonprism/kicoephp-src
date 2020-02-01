<?php
// 异常展示

namespace kicoe\core;

class Exception extends \Exception
{

    private $ex_tpl_start = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Exception</title>
        <meta name="robots" content="noindex,nofollow" />
    ';

    // custom error page html head
    protected $ex_tpl_head = '
        <style>
            .er { width:440px;margin:120px auto; }
            .er div span{ 
                display: block; 
                padding: 10px; 
                font-size: 17px;
                color: #858585;
                box-shadow: 0px 1px 0px #dedede;
                letter-spacing: 1px; 
               }
            .er div p{ padding: 0 12px; }
            em{ font-family:"MS Gothic"; display:block;text-align:center;margin-bottom:16px; }
            .type {
                color: #FF4040;
                text-align: center;
                font-size: 20px;
                margin-bottom: 15px;
            }
        </style>
    ';

    private $ex_tpl_end ='
        </head>
        <body>
        <div class="er">
            <div class="type"> %s </div>
            <div class="info"> <span> Info </span> <p>%s</p> </div>
            <div class="file"> <span> File </span> <p>%s</p> </div>
            <div class="line"> <span> Line </span> <p>%s</p> </div>
        </div>
        </body>
        </html>
    ';

    // 报错类型
    protected $ex_type;

    /**
     * 和原来相比新增一个报错类型
     * 主要构造参数有
     * @param string $file 报错文件名
     * @param int $line 报错行数
     * @param string message 报错信息
     */
    public function __construct(
        $type = null, 
        $message = null, 
        $file = null, 
        $line = null, 
        $code = 0, 
        Exception $previous = null
    )
    {
        $this->ex_type = $type;
        $this->file = $file;
        $this->line = $line;
        parent::__construct($message, $code, $previous);
    }

    /**
     * 用于显示错误信息
     */
    public function show()
    {
        header("HTTP/1.1 500 $this->message");

        Response::getInstance()->status('500')->send(
            $this->ex_tpl_start.$this->ex_tpl_head.sprintf(
                $this->ex_tpl_end,
                $this->ex_type,
                $this->message,
                $this->file,
                $this->line
            )
        );
    }

}
