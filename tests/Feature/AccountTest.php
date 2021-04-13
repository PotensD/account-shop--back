<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AccountAction;
use App\Models\AccountInfo;
use App\Models\AccountType;
use App\Models\Rule;
use App\Models\Role;
use Illuminate\Support\Str;
use App\Models\Game;
use App\Models\Account;
use Illuminate\Http\UploadedFile;

class AccountTest extends TestCase
{
    public function makeIdealGame()
    {
        $user = User::factory()->make();
        $user->save();
        $this->actingAs($user);

        $game = Game::create([
            'publisher_name' => 'Sohagame',
            'name' => Str::random(40),
            'slug' => Str::random(40),
            'image_path' => Str::random(40),
        ]);
        $game->rolesCanCreatedGame()->sync('tester');

        $x = rand(2, 5);
        for ($zz = 0; $zz < $x; $zz++) {
            $accountType = AccountType::create([
                'name' => Str::random(40),
                'slug' => Str::random(40),
                'game_id' => $game->id,
            ]);
            $accountType->allowRole('tester', rand(0, 1) ? 0 : 440);

            $rand = rand(5, 10);
            for ($i = 0; $i < $rand; $i++) {
                $rule = Rule::create(['required' => true]);
                $accountInfo = AccountInfo::create([
                    'name' => Str::random(40),
                    'slug' => Str::random(40),
                    'rule_id' => $rule->id,
                    'account_type_id' => $accountType->id,
                ]);
                $accountInfo->rolesNeedFilling()->attach('tester');
            }

            $rand = rand(5, 10);
            for ($nn = 0; $nn < $rand; $nn++) {
                $accountAction = AccountAction::create([
                    'name' => Str::random(40),
                    'slug' => Str::random(40),
                    'video_path' => Str::random(40),
                    'account_type_id' => $accountType->id,
                    'required' => true,
                ]);
                $accountAction->rolesThatNeedPerformingAccountAction()->attach('tester');
            }
        }

        return $game;
    }

    public function makeDataForAccountInfos(AccountType $accountType)
    {
        $accountInfos = $accountType->accountInfosThatRoleNeedFilling(Role::find('tester'));
        $data = [];

        foreach ($accountInfos as $accountInfo) {
            if ($accountInfo->rule->required) {
                $data['id' . $accountInfo->getKey()] = Str::random(40);
            }
        }

        return $data;
    }

    public function makeDataForAccountActions(AccountType $accountType)
    {
        $accountActions = $accountType->accountActionsThatRoleNeedPerforming(Role::find('tester'));
        $data = [];

        foreach ($accountActions as $accountAction) {
            if ($accountAction->required) {
                $data['id' . $accountAction->getKey()] = true;
            }
        }

        return $data;
    }

    public function testStore()
    {
        $user = User::factory()->make();
        $user->save();
        $user->givePermissionTo('create_account');
        $user->assignRole('tester');
        $user->refresh();
        $game = $this->makeIdealGame();

        for ($aBc = 0; $aBc < 5; $aBc++) {
            $accountType = $game->accountTypes->random();
            $route = route('account.store', ['accountType' => $accountType]);
            $dataOfAccountActions = $this->makeDataForAccountActions($accountType);
            $dataOfAccountInfos = $this->makeDataForAccountInfos($accountType);
            $data = [
                'roleKey' => 'tester',
                'username' => Str::random(60),
                'password' => Str::random(60),
                'price' => rand(20000, 50000),
                'description' => Str::random(100),
                'representativeImage' => UploadedFile::fake()->image('avatar.jpg'),
                'images' => [
                    UploadedFile::fake()->image('avatar343243.jpg'),
                    UploadedFile::fake()->image('avatar4324.jpg'),
                ],
                'accountInfos' => $dataOfAccountInfos,
                'accountActions' => $dataOfAccountActions,
            ];

            $res = $this->actingAs($user)
                ->json('post', $route, $data);
            $res->assertStatus(201);
            $res->assertJson(
                fn ($j) => $j
                    ->has(
                        'data',
                        fn ($j) => $j
                            ->where('username', $data['username'])
                            ->where('password', $data['password'])
                            ->where('price', $data['price'])
                            ->where('description', $data['description'])
                            ->has('representativeImagePath')
                            ->has('images.' . array_key_last($data['images']))
                            ->has('infos.' . (count($data['accountInfos']) - 1))
                            ->has('actions.' . (count($data['accountActions']) - 1))
                            ->etc()
                    )
            );
        }

        $accountType = $game->accountTypes->random();
        $route = route('account.store', ['accountType' => $accountType]);
        $dataOfAccountActions = $this->makeDataForAccountActions($accountType);
        $dataOfAccountInfos = $this->makeDataForAccountInfos($accountType);
        $data = [
            'roleKey' => 'tester',
            'username' => Str::random(60),
            'password' => Str::random(60),
            'price' => rand(20000, 50000),
            'description' => Str::random(100),
            'representativeImage' => UploadedFile::fake()->image('avatar.jpg'),
            'images' => [
                UploadedFile::fake()->image('avatar343243.jpg'),
                UploadedFile::fake()->image('avatar4324.jpg'),
            ],
            'accountInfos' => $dataOfAccountInfos,
            'accountActions' => $dataOfAccountActions,
        ];
        $intactData = $data;

        # Case: lack accountInfo
        $firstKeyAccountInfo = array_key_first($data['accountInfos']);
        unset($data['accountInfos'][$firstKeyAccountInfo]);
        $res = $this->actingAs($user)
            ->json('post', $route, $data);
        $res->assertStatus(422);
        $res->assertJson(
            fn ($j) => $j
                ->has('errors.accountInfos.' . $firstKeyAccountInfo)
                ->etc()
        );

        # Case: lack accountAction
        $data = $intactData;
        $firstKeyAccountAction = array_key_first($data['accountActions']);
        unset($data['accountActions'][$firstKeyAccountAction]);
        $res = $this->actingAs($user)
            ->json('post', $route, $data);
        $res->assertStatus(422);
        $res->assertJson(
            fn ($j) => $j
                ->has('errors.accountActions.' . $firstKeyAccountAction)
                ->etc()
        );

        # Case: invalid roleKey
        $data = $intactData;
        $data['roleKey'] = Str::random(10);
        $res = $this->actingAs($user)
            ->json('post', $route, $data);
        $res->assertStatus(422);
        $res->assertJson(
            fn ($json) => $json
                ->has('errors.roleKey')
                ->etc()
        );
    }

    public function testUpdate()
    {
        $account = Account::inRandomOrder()->first();
        $creator = $account->creator;
        $creator->givePermissionTo('update_account');
        $creator->refresh();

        $accountType = $account->accountType;
        $route = route('account.update', ['account' => $account]);

        $data = [
            'roleKey' => 'tester',
            'username' => Str::random(60),
            'password' => Str::random(60),
            'price' => rand(20000, 50000),
            'description' => Str::random(100),
            'representativeImage' => UploadedFile::fake()->image('avatar.jpg'),
            'images' => [
                UploadedFile::fake()->image('avatar343243.jpg'), UploadedFile::fake()->image('avatar4324.jpg')
            ],
            'accountInfos' =>  $this->makeDataForAccountInfos($accountType),
            'accountActions' => $this->makeDataForAccountActions($accountType),
        ];
        $res = $this->actingAs($creator)
            ->json('put', $route, $data);
        $res->assertStatus(200);
        $res->assertJson(
            fn ($j) => $j
                ->has(
                    'data',
                    fn ($j) => $j
                        ->where('username', $data['username'])
                        ->where('password', $data['password'])
                        ->where('price', $data['price'])
                        ->where('description', $data['description'])
                        ->has('representativeImagePath')
                        ->has('images.' . array_key_last($data['images']))
                        ->has('infos.' . (count($data['accountInfos']) - 1))
                        ->has('actions.' . (count($data['accountActions']) - 1))
                        ->etc()
                )
        );
        $intactData = $data;

        # Case: lack accountInfo
        $firstKeyAccountInfo = array_key_first($data['accountInfos']);
        unset($data['accountInfos'][$firstKeyAccountInfo]);
        $res = $this->actingAs($creator)
            ->json('put', $route, $data);
        $res->assertStatus(422);
        $res->assertJson(
            fn ($j) => $j
                ->has('errors.accountInfos.' . $firstKeyAccountInfo)
                ->etc()
        );

        # Case: lack accountAction
        $data = $intactData;
        $firstKeyAccountAction = array_key_first($data['accountActions']);
        unset($data['accountActions'][$firstKeyAccountAction]);
        $res = $this->actingAs($creator)
            ->json('put', $route, $data);
        $res->assertStatus(422);
        $res->assertJson(
            fn ($j) => $j
                ->has('errors.accountActions.' . $firstKeyAccountAction)
                ->etc()
        );

        # Case: invalid roleKey
        $data = $intactData;
        $data['roleKey'] = Str::random(10);
        $res = $this->actingAs($creator)
            ->json('put', $route, $data);
        $res->assertStatus(422);
        $res->assertJson(
            fn ($json) => $json
                ->has('errors.roleKey')
                ->etc()
        );
    }

    public function testApprove()
    {
        foreach ([1, 2] as $nNnO) {
            $account = Account::inRandomOrder()
                ->where('status_code', '>=', 0)
                ->where('status_code', '<=', 99)
                ->first();
            $route = route('account.approve', ['account' => $account]);
            $user = User::factory()->make();
            $user->save();
            $user->givePermissionTo('approve_account');
            $user->refresh();

            $res = $this->actingAs($user)
                ->json('post', $route);

            $res->assertStatus(200);
            $res->assertJson(
                fn ($j) => $j
                    ->where('data.statusCode', 480)
            );
        }
    }

    public function testShow()
    {
        $account = Account::inRandomOrder()->first();

        $route = route('account.show', ['account' => $account]);

        $res = $this->json('get', $route);

        $res->assertStatus(200);

        $res->assertJson(
            fn ($j) => $j
                ->has(
                    'data',
                    fn ($j) => $j
                        ->where('id', $account->id)
                        ->where('username', $account->username)
                        ->where('password', $account->password)
                        ->where('price', $account->price)
                        ->where('statusCode', $account->status_code)
                        ->where('description', $account->description)
                        ->has('representativeImagePath')
                        ->has('lastRoleKeyCreatorUsed')
                        ->has('images')
                        ->has('game')
                        ->has('accountType')
                        ->has('lastUpdatedEditor')
                        ->has('creator')
                        ->has('censor')
                        ->has('type')
                        ->has('infos')
                        ->has('actions')
                        ->has('approvedAt')
                        ->has('updatedAt')
                        ->has('createdAt')
                )
        );
    }

    public function testBuy()
    {
        $account = Account::inRandomOrder()
            ->where('status_code', '>=', 400)
            ->where('status_code', '<=', 499)
            ->first();


        $route = route('account.buy', ['account' => $account]);
        $user = User::factory()->make();
        $goldCoin = rand($account->price, $account->price + 200000);
        $user->gold_coin = $goldCoin;
        $user->save();

        # Case: enough gold coin to buy account
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(200);
        $res->assertJson(
            fn ($j) => $j
                ->where('data.username', $account->username)
                ->where('data.password', $account->password)
        );

        $res = $this->actingAs($user)
            ->json('get', route('profile.show'));
        $res->assertJson(
            fn ($j) => $j
                ->where('data.goldCoin',  $goldCoin - $account->price)
        );

        $account = Account::inRandomOrder()
            ->where('status_code', '>=', 400)
            ->where('status_code', '<=', 499)
            ->first();
        $route = route('account.buy', ['account' => $account]);
        # Case: don't enough gold coin to buy account
        $user->gold_coin = rand(1, $account->price - 1);
        $user->save();
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(501);
    }

    public function testStoreRouteMiddleware()
    {
        $game = $this->makeIdealGame();
        $accountType = $game->accountTypes->random();
        $route = route('account.store', ['accountType' => $accountType]);

        /**
         * Not auth
         * -------------------
         */
        $res = $this->json('post', $route);
        $res->assertStatus(403);

        /**
         * Is auth
         * ---------------------
         * create - can use account type
         */
        $user = User::factory()->make();
        $user->save();

        # 0 - 0
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(403);

        # 0 - 1
        $user->assignRole('tester');
        $user->refresh();
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(403);

        # 1 - 0
        $user->givePermissionTo('create_account');
        $user->removeRole('tester');
        $user->refresh();
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(403);

        # 1 - 1
        $user->assignRole('tester');
        $user->refresh();
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(422);
    }

    public function testApproveRouteMiddleware()
    {
        $account = Account::inRandomOrder()
            ->where('status_code', '>=', 0)
            ->where('status_code', '<=', 99)
            ->first();
        $invalidAccount = Account::inRandomOrder()
            ->where('status_code', '>=', 100)
            ->first();
        $route = route('account.approve', ['account' => $account]);
        $invalidRoute = route('account.approve', ['account' => $invalidAccount]);

        /**
         * Not auth
         * -------------------
         */
        $res = $this->json('post', $route);
        $res->assertStatus(401);

        /**
         * Is auth
         * ---------------------
         * approve - account is valid
         */
        $user = User::factory()->make();
        $user->save();

        # 0 - 0
        $res = $this->actingAs($user)
            ->json('post', $invalidRoute);
        $res->assertStatus(403);

        # 0 - 1
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(403);

        # 1 - 0
        $user->givePermissionTo('approve_account');
        $user->refresh();
        $res = $this->actingAs($user)
            ->json('post', $invalidRoute);
        $res->assertStatus(403);

        # 1 - 1
        $user->refresh();
        $res = $this->actingAs($user)
            ->json('post', $route);
        $res->assertStatus(200);
    }

    public function testBuyRouteMiddleware()
    {
        $validAccount = Account::where('status_code', '>=', 400)
            ->where('status_code', '<=', 499)
            ->first();
        $boughtAccount = Account::where('status_code', '>=', 800)
            ->first();
        $invalidAccount = Account::where('status_code', '<', 400)
            ->orWhere('status_code', '>', 499)
            ->first();

        /**
         * Not auth
         * ------------
         */

        /**
         * Auth as creator
         * -------------
         */

        # valid account
        $this->actingAs($validAccount->creator)
            ->json('post', route('account.buy', ['account' => $validAccount]))
            ->assertStatus(403);

        # invalid account
        $this->actingAs($invalidAccount->creator)
            ->json('post', route('account.buy', ['account' => $invalidAccount]))
            ->assertStatus(403);

        # bought account
        $this->actingAs($boughtAccount->creator)
            ->json('post', route('account.buy', ['account' => $boughtAccount]))
            ->assertStatus(403);


        /**
         * Auth as regular user
         * -------------
         */
        $user = User::factory()->make();
        $user->save();

        # valid account
        $this->actingAs($user)
            ->json('post', route('account.buy', ['account' => $validAccount]))
            ->assertStatus(501);

        # invalid account
        $this->actingAs($user)
            ->json('post', route('account.buy', ['account' => $invalidAccount]))
            ->assertStatus(403);

        # bought account
        $this->actingAs($user)
            ->json('post', route('account.buy', ['account' => $boughtAccount]))
            ->assertStatus(403);
    }
}
