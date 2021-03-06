<?php

/*
 * Laravel-Mns -- 阿里云消息队列（MNS）的 Laravel 适配。
 *
 * This file is part of the milkmeowo/laravel-mns.
 *
 * (c) Milkmeowo <milkmeowo@gmail.com>
 * @link: https://github.com/milkmeowo/laravel-queue-aliyun-mns
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Milkmeowo\LaravelMns\Jobs;

use Illuminate\Queue\Jobs\Job;
use Illuminate\Container\Container;
use AliyunMNS\Exception\MnsException;
use Milkmeowo\LaravelMns\Adaptors\MnsAdapter;
use AliyunMNS\Responses\ReceiveMessageResponse;
use Illuminate\Contracts\Queue\Job as JobContract;

class MnsJob extends Job implements JobContract
{
    /**
     * 任务
     *
     * @var \AliyunMNS\Responses\ReceiveMessageResponse
     */
    protected $job;
    /**
     * Mns 适配器.
     *
     * @var \Milkmeowo\LaravelMns\Adaptors\MnsAdapter
     */
    private $mns;

    /**
     * Job 构造.
     *
     * @param \Illuminate\Container\Container             $container Laravel容器
     * @param \Milkmeowo\LaravelMns\Adaptors\MnsAdapter   $mns       Mns 适配器
     * @param string                                      $queue     队列
     * @param \AliyunMNS\Responses\ReceiveMessageResponse $job       任务
     */
    public function __construct(Container $container, MnsAdapter $mns, $queue, ReceiveMessageResponse $job)
    {
        $this->container = $container;
        $this->mns = $mns;
        $this->queue = $queue;
        $this->job = $job;
    }

    /**
     * 获取 Job 的 RawBody.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->getMessageBody();
    }

    /**
     * 从队列中删除.
     */
    public function delete()
    {
        try {
            $receiptHandle = $this->job->getReceiptHandle();
            $this->mns->useQueue($this->queue)->deleteMessage($receiptHandle);
            // 删除成功
            $this->deleted = true;
        } catch (MnsException $exception) {
            // 删除失败
            $this->deleted = false;
        }
    }

    /**
     * 释放 Job，重新回到队列.
     *
     * @param int $delay 延迟时间
     */
    public function release($delay = 0)
    {
        // 默认情况下 Laravel 将以 delay 0 来更改可见性，其预期的是使用队列服务默认的
        // 下次可消费时间，但 Aliyun MNS PHP SDK 的接口要求这个值必须大于 0，
        // 指从现在起，多久后消息变为可消费。
        if ($delay == 0) {
            $delay = $this->fromNowToNextVisibleTime($this->job->getNextVisibleTime());
        }
        parent::release($delay);
        $this->mns->useQueue($this->queue)->changeMessageVisibility($this->job->getReceiptHandle(), $delay);
    }

    /**
     * 从现在起到消息变为可消费的秒数。
     *
     * @param int $nextVisibleTime 下次可消费时的毫秒时间戳。
     *
     * @return int
     */
    private function fromNowToNextVisibleTime($nextVisibleTime)
    {
        $nowInMilliSeconds = 1000 * microtime(true);
        $fromNowToNextVisibleTime = $nextVisibleTime - $nowInMilliSeconds;
        $fromNowToNextVisibleTime = (int) ($fromNowToNextVisibleTime / 1000);

        return $fromNowToNextVisibleTime > 0 ? $fromNowToNextVisibleTime : 1;
    }

    /**
     * Job 尝试次数.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job->getDequeueCount();
    }

    /**
     * 获取 Job Id.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->getMessageId();
    }
}
