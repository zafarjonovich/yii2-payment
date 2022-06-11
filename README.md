## Yii2 uchun to'lov tizimlariga bog'lanish paketi

Assalomu aleykum, yaqinda bir ajoyib proektni qildim, unga PAYME to'lov tizimini ulashim keragidi, PAYME ga oldin ulanmaganman. Github ni qarasam yii2 uchun yaxshi paketni topa olmadim, shuning uchun o'zim va boshqalar ishlatishi uchun open source yozdim, bu paket sizlar uchun ham foydali bo'ladi degan umiddaman.

____


### O'rnatish

#### Paketni o'rnatish

[Composer](http://getcomposer.org/download/) orqali tavsiya qilingan o'rnatish

Quyidagi komandani yurg'izing

```
composer require zafarjonovich/yii2-payment
```

yoki `composer.json` ga quyidagini qo'shing

```
"zafarjonovich/yii2-payment": "*"
```

#### Paketni sozlash

Paket ishlashi uchun quyidagi migratsiyani yurg'izish kerak

```
php yii migrate --migrationPath="@vendor/zafarjonovich/yii2-payment/src/migrations"
```

---


### Ishlatish

Bu paketda to'lov tizimlari bilan gaplashish biznes logikada butunlay ajratilgan.

#### Paymega ulanish

Sizga misol sifatida quyidagi xolatni ko'rsataman, masalan sizda Invoice nomli model bor va bizning biznes logika ham aynan shu modelga bog'liq, biz proektning api moduliga PaymeController nomli kontroller ochdik va unda quyidagi method larni qayta yozishimiz kerak'

- getCredentials -> Credentials obyekti qaytadi PAYME ga bog'lanish uchun
- getOwnerIdByAccount -> PAYME dan keluvchi rekvizitlarga qarab owner id ya'ni model id sini qaytaradi
- checkPerformTransaction -> Tranzaksiyani tekshirish
- performTransaction -> Tranzaksiyani omadli o'tdi
- cancelTransaction -> Tranzaksiya bekor qilindi

```php
<?php

namespace api\controllers;


use common\models\Invoice;
use zafarjonovich\Yii2Payment\gateways\payme\base\Credentials;
use zafarjonovich\Yii2Payment\gateways\payme\controllers\Controller;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\AccountNotFoundException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\RequestParseException;

class PaymeController extends Controller
{
    public function getCredentials()
    {
        return new Credentials([
            'login' => env('PAYME_LOGIN'),
            'password' => env('PAYME_PASSWORD')
        ]);
    }

    public function getOwnerIdByAccount($account)
    {
        if (!isset($account['invoice_id'])) {
            throw new RequestParseException('invoice id not found in request');
        }

        $invoice = Invoice::findOne(['id' => $account['invoice_id']]);

        if (null === $invoice || $invoice->status != Invoice::STATUS_SENT) {
            throw new AccountNotFoundException('invoice not found');
        }

        return $invoice->id;
    }

    protected function checkPerformTransaction($ownerId, $amount)
    {
        $invoice = Invoice::findOne(['id' => $ownerId]);
        return $invoice->price * 100 == $amount;
    }

    protected function performTransaction($ownerId)
    {
        $invoice = Invoice::findOne(['id' => $ownerId]);
        $invoice->status = Invoice::STATUS_PAID;
        $invoice->save(false);
    }

    protected function cancelTransaction($ownerId)
    {
        $invoice = Invoice::findOne(['id' => $ownerId]);
        $invoice->status = Invoice::STATUS_CANCELED;
        $invoice->save(false);
    }
}

```


#### Ishlatish

PAYME hamma so'rovlarini bitta url ga yuboradi. Bizning xolatda quyidagi url ga so'rovlarni yuborsa bo'ladi

`https://api.domain.com/payme/hook`

Agar siz so'rovning metodini boshqacha nomlamoqchi bo'lsangiz quyidagi ishni bajarishingiz kerak

```php
<?php

namespace api\controllers;


use common\models\Invoice;
use zafarjonovich\Yii2Payment\gateways\payme\base\Credentials;
use zafarjonovich\Yii2Payment\gateways\payme\controllers\Controller;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\AccountNotFoundException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\RequestParseException;

class PaymeController extends Controller
{
    ...
    
    public function actionUpdate(){
        return parent::actionHook();
    }
    
    ...
}
```

Endi so'rovlarni quyidagicha ishlatsangiz bo'ladi

`https://api.domain.com/payme/update`