<?php


namespace app\app\controller;


use app\common\RedisHelper;
use app\model\Book;
use app\model\Chapter;
use app\model\UserBuy;
use app\model\UserFinance;
use app\model\UserOrder;
use app\pay\Pay;
use app\service\FinanceService;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;

class Finance extends BaseAuth
{
    protected $financeService;

    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->financeService = app('financeService');

    }

    public function getBalance()
    {
        $balance = cache('balance:' . $this->uid); //当前用户余额
        if (!$balance) {
            $balance = $this->financeService->getBalance($this->uid);
            cache('balance:' . $this->uid, $balance, '', 'pay');
        }

        $result = [
            'success' => 1,
            'balance' => $balance
        ];
        return json($result);
    }

    public function getCharges()
    {
        $map[] = ['user_id', '=', $this->uid];
        $map[] = ['usage', '=', 1];
        $charges = UserFinance::where($map)->order('id', 'desc')->limit(10)->select();

        return json(['success' => 1, 'charges' => $charges]);
    }

    public function getSpendings()
    {
        $map[] = ['user_id', '=', $this->uid];
        $map[] = ['usage', 'in', [2, 3]];
        $spendings = UserFinance::where($map)->order('id', 'desc')->limit(10)->select();
        return json(['success' => 1, 'spendings' => $spendings]);
    }

    public function buyhistory()
    {
        $buys = UserBuy::where('user_id', '=', $this->uid)->order('id', 'desc')->limit(50)->select();
        try {
            foreach ($buys as &$buy) {
                $chapter = Chapter::findOrFail($buy['chapter_id']);
                $book = Book::findOrFail($buy['book_id']);
                if ($this->end_point == 'id') {
                    $book['param'] = $book['id'];
                } else {
                    $book['param'] = $book['unique_id'];
                }
                $buy['chapter'] = $chapter;
                $buy['book'] = $book;
            }
        } catch (DataNotFoundException $e) {
            return json(['success' => 0, 'msg' => $e->getMessage()]);
        } catch (ModelNotFoundException $e) {
            return json(['success' => 0, 'msg' => $e->getMessage()]);
        }
        return json(['success' => 1, 'buys' => $buys]);
    }

    //处理充值
    public function charge()
    {
        $money = request()->post('money'); //用户充值金额
        $pay_type = request()->post('pay_type'); //充值渠道
        $pay_code = request()->post('code');
        $order = new UserOrder();
        $order->user_id = $this->uid;
        $order->money = $money;
        $order->status = 0; //未完成订单
        $order->pay_type = 0;
        $order->expire_time = time() + 86400; //订单失效时间往后推一天
        $res = $order->save();
        if ($res) {
            $pay = new Pay();
            $pay->submit('xwx_order_' . $order->id, $money, $pay_type, $pay_code); //调用功能类，进行充值处理
        }
        return json(['success' => 1, 'msg' => '调用充值接口']);
    }

    public function buychapter()
    {
        $id = input('chapter_id');
        $chapter = Chapter::with('book')->cache('buychapter:' . $id, 600, 'redis')->find($id);
        $price = $chapter->book->money; //获得单章价格
        $redis = RedisHelper::GetInstance();
        if (!$redis->exists($this->redis_prefix . ':user_buy_lock:' . $this->uid)) { //如果没上锁，则该用户可以进行购买操作
            $balance = $this->financeService->getBalance($this->uid); //这里不查询缓存，直接查数据库更准确
            if ($price > $balance) { //如果价格高于用户余额，则不能购买
                return json(['success' => 0, 'msg' => '余额不足，请充值']);
            } else {
                $userFinance = new UserFinance();
                $userFinance->user_id = $this->uid;
                $userFinance->money = $price;
                $userFinance->usage = 3;
                $userFinance->summary = '购买章节';
                $userFinance->save();

                $userBuy = new UserBuy();
                $userBuy->user_id = $this->uid;
                $userBuy->chapter_id = $id;
                $userBuy->book_id = $chapter->book_id;
                $userBuy->money = $price;
                $userBuy->summary = '购买章节';
                $userBuy->save();
            }
            $redis->set($this->redis_prefix . ':user_buy_lock:' . $this->uid, 1, 5);
            Cache::clear('pay'); //删除缓存
            $balance = $balance - $price; //算出购买后的余额
            return json(['success' => 1, 'msg' => '购买成功，等待跳转', 'balance' => $balance]);
        } else {
            return json(['success' => 0, 'msg' => '同账号非法操作']);
        }
    }
}