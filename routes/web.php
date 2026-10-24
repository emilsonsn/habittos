<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\WorkspaceController;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ambientes');
Route::middleware('guest')->group(function () {
    Route::view('/entrar', 'auth.login')->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/cadastro', 'auth.register')->name('register');
    Route::post('/cadastro', [AuthController::class, 'register'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::get('/ambientes', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/ambientes', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/ambientes/selecionar', [WorkspaceController::class, 'select'])->name('workspaces.select');
    Route::prefix('ambientes/{workspace}')->middleware('workspace')->group(function () {
        Route::get('/configuracoes', fn (Workspace $workspace) => view('settings.index', compact('workspace')))->name('settings.index');
        Route::post('/financeiro/abrir-mes', [FinanceController::class, 'openMonth'])->middleware(['workspace:finance.view', 'workspace:finance.create', 'workspace:budget.manage'])->name('finance.open-month');
        Route::get('/financeiro', [FinanceController::class, 'index'])->middleware('workspace:finance.view')->name('finance.index');
        Route::get('/lancamentos/novo', [FinanceController::class, 'create'])->middleware('workspace:finance.create')->name('entries.create');
        Route::post('/lancamentos', [FinanceController::class, 'store'])->middleware('workspace:finance.create')->name('entries.store');
        Route::get('/lancamentos/{entry}/editar', [FinanceController::class, 'edit'])->middleware('workspace:finance.update')->name('entries.edit');
        Route::put('/lancamentos/{entry}', [FinanceController::class, 'update'])->middleware('workspace:finance.update')->name('entries.update');
        Route::delete('/lancamentos/{entry}', [FinanceController::class, 'destroy'])->middleware('workspace:finance.delete')->name('entries.destroy');
        Route::put('/orcamento', [FinanceController::class, 'budget'])->middleware('workspace:budget.manage')->name('budget.update');
        Route::post('/categorias', [FinanceController::class, 'category'])->middleware('workspace:budget.manage')->name('categories.store');
        Route::get('/membros', [WorkspaceController::class, 'members'])->middleware('workspace:members.manage')->name('members.index');
        Route::post('/membros', [WorkspaceController::class, 'saveMember'])->middleware('workspace:members.manage')->name('members.store');
        Route::delete('/membros/{user}', [WorkspaceController::class, 'removeMember'])->middleware('workspace:members.manage')->name('members.destroy');
        Route::get('/metas', [GoalController::class, 'index'])->middleware('workspace:goals.view')->name('goals.index');
        Route::post('/metas', [GoalController::class, 'store'])->middleware('workspace:goals.manage')->name('goals.store');
        Route::put('/metas/{goal}', [GoalController::class, 'update'])->middleware('workspace:goals.manage')->name('goals.update');
        Route::delete('/metas/{goal}', [GoalController::class, 'destroy'])->middleware('workspace:goals.manage')->name('goals.destroy');
    });
});
