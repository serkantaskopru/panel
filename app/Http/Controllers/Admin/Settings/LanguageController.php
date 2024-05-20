<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\Language\LanguageCreateRequest;
use App\Http\Requests\Admin\Settings\Language\LanguageUpdateRequest;
use App\Actions\Admin\Settings\Language\Create;
use App\Actions\Admin\Settings\Language\Update;
use App\Actions\Admin\Settings\Language\Delete;
use App\Actions\Admin\Settings\Language\GetAll;
use App\Actions\Admin\Settings\Language\GetOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LanguageController extends Controller
{
    private $getAll;
    private $getOne;
    private $create;
    private $update;
    private $delete;

    public function __construct(
        GetAll $getAll,
        GetOne $getOne,
        Create $create,
        Update $update,
        Delete $delete
    ) {
        $this->getAll = $getAll;
        $this->getOne = $getOne;
        $this->create = $create;
        $this->update = $update;
        $this->delete = $delete;
    }

    public function index(): View
    {
        $languages = $this->getAll->execute();
        return view('admin.settings.languages.index', compact('languages'));
    }

    public function create(): View
    {
        return view('admin.settings.languages.create');
    }

    public function store(LanguageCreateRequest $request): RedirectResponse
    {
        $this->create->execute($request->validated());
        return redirect()->route('panel.settings.languages')->with('success', __('Dil başarılı bir şekilde eklendi.'));
    }

    public function edit($id): View
    {
        $language = $this->getOne->execute($id);
        return view('admin.settings.languages.edit', compact('language'));
    }

    public function update(LanguageUpdateRequest $request, $id): RedirectResponse
    {
        $language = $this->getOne->execute($id);
        $newStatus = $request->input('status');
        $oldStatus = $language->status->value;

        if ($newStatus != $oldStatus && $this->isDefault($language)) {
            return redirect()->back()->with('error', __('Seçili dil genel dil olarak tanımlı. Genel dilin durumunu değiştiremezsiniz.'));
        }

        $this->update->execute($id, $request->validated());
        return redirect()->route('panel.settings.languages')->with('success', __('Dil başarılı bir şekilde güncellendi.'));
    }

    public function destroy($id): RedirectResponse
    {
        $language = $this->getOne->execute($id);

        if(!$language) {
            return redirect()->back()->with('error', __('Lütfen geçerli bir dil seçiniz.'));
        }

        if ($this->isDefault($language)) {
            return redirect()->back()->with('error', __('Seçili dil sistem dili olarak tanımlı. Lütfen önce sistem ayarlarından bu değeri değiştiriniz.'));
        }

        $this->delete->execute($id);
        return redirect()->route('panel.settings.languages')->with('success', __('Dil başarılı bir şekilde silindi'));
    }

    private function isDefault($language): bool
    {
        return config('panel.language') === $language->code;
    }

}