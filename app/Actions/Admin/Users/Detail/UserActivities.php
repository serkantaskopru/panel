<?php

namespace App\Actions\Admin\Users\Detail;

use App\Services\Admin\Users\Detail as Service;

class UserActivities
{
    protected $postService;

    /**
     * GetAll işlemi için gerekli Service bağımlılığı enjekte edilir.
     *
     * @param Service $postService
     */
    public function __construct(Service $postService)
    {
        $this->postService = $postService;
    }

    /**
     * Kullanıcıya ait oturum kayıtlarını getirir.
     *
     * @return mixed Oturum kayıtları listesi
     */
    public function execute($id)
    {
        $activities = $this->postService->userActivities($id);
        return $activities;
    }
}
