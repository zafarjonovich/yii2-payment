<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\base;

use zafarjonovich\Yii2Payment\gateways\payme\exceptions\RequestParseException;

class Request
{
    protected $data;

    public static function load()
    {
        $post = \Yii::$app->request->post();

        if (!isset($post['method'])) {
            throw new RequestParseException('Method not found in request');
        }
        if (!isset($post['params'])) {
            throw new RequestParseException('Params not found in request');
        }

        $request = new Request();
        $request->data = $post;

        return $request;
    }

    public function getMethod()
    {
        return $this->data['method'];
    }

    /**
     * @return RequestParams
     */
    public function getParams()
    {
        return new RequestParams($this->data['params']);
    }
}