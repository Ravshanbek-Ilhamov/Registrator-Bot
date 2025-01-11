<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BotController extends Controller
{
    public function store(int $chatId, string $text, $replyMarkup = null)
    {
        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post($token . '/sendMessage', $payload);
    }

    public function bot(Request $request)
    {
        try {
            $data = $request->all();
            $chat_id = $data['message']['chat']['id'];
            $text = $data['message']['text'] ?? null;
            $photo = $data['message']['photo'] ?? null;
            Log::info($chat_id);

            if ($text === '/start') {
                $this->store($chat_id, "Assalomu alaykum! Iltimos, tanlang:", [
                    'keyboard' => [
                        [
                            ['text' => 'Register'],
                            ['text' => 'Login']
                        ]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]);
                return;
            }
            if ($text === 'Register') {
                Cache::put("register_step_{$chat_id}", 'name');
                $this->store($chat_id, "Iltimos, ismingizni kiriting:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'name') {
                Cache::put("register_name_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'email');
                $this->store($chat_id, "Iltimos, elektron pochta manzilingizni kiriting:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'email') {
                Cache::put("register_email_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'password');
                $this->store($chat_id, "Iltimos, parolingizni kiriting:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'password') {
                Cache::put("register_password_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'confirmation_code');

                $confirmation_code = Str::random(6);

                $email = Cache::get("register_email_{$chat_id}");
                $name = Cache::get("register_name_{$chat_id}");

                try {
                    Mail::to($email)->send(new SendCode($name, $confirmation_code));
                    Log::info('Email sent successfully');
                    $this->store($chat_id, "Emailizga tasdiqlash kodi yuborildi. Iltimos, uni kiriting.");
                } catch (\Exception $e) {
                    Log::error('Email sending failed: ' . $e->getMessage());
                    $this->store($chat_id, "Tasdiqlash kodi yuborishda xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.");
                }

                Cache::put("confirmation_code_{$chat_id}", $confirmation_code);
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'confirmation_code') {
                if ($text === Cache::get("confirmation_code_{$chat_id}")) {
                    Cache::put("register_password_{$chat_id}", bcrypt(Cache::get("register_password_{$chat_id}")));
                    Cache::put("register_step_{$chat_id}", 'image');
                    $this->store($chat_id, "Tasdiqlash kodi to'g'ri. Iltimos, profilingiz uchun rasm yuboring.");
                    Cache::forget("confirmation_code_{$chat_id}");
                } else {
                    $this->store($chat_id, "Tasdiqlash kodi noto'g'ri. Iltimos, to'g'ri kodi kiriting.");
                }
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'image') {
                if ($photo) {
                    $file_id = end($photo)['file_id'];

                    $telegram_api = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                    $file_path_response = file_get_contents("{$telegram_api}/getFile?file_id={$file_id}");
                    $response = json_decode($file_path_response, true);

                    if (isset($response['result']['file_path'])) {
                        $file_path = $response['result']['file_path'];
                        $download_url = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/{$file_path}";

                        $image_name = uniqid() . '.jpg';
                        $image_content = file_get_contents($download_url);

                        if ($image_content) {
                            Storage::disk('public')->put("uploads/{$image_name}", $image_content);
                            $image_path = "uploads/{$image_name}";
                        } else {
                            $this->store($chat_id, "Rasmni yuklab olishda xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.");
                            return;
                        }
                        $user = User::create([
                            'name' => Cache::get("register_name_{$chat_id}"),
                            'email' => Cache::get("register_email_{$chat_id}"),
                            'password' => Cache::get("register_password_{$chat_id}"),
                            'chat_id' => $chat_id,
                            'image' => "uploads/{$image_name}",
                            'email_verified_at' => Carbon::now(),
                        ]);

                        $admin = User::where('role', 'admin')->first();

                        if ($admin) {
                            $userData = "Foydalanuvchi nomi: " . Cache::get("register_name_{$chat_id}") . "\n" .
                                "Email: " . Cache::get("register_email_{$chat_id}") . "\n" .
                                "Chat ID: " . $chat_id;

                            $replyMarkup = [
                                'keyboard' => [
                                    [
                                        ['text' => 'Tasdiqlash✅'],
                                        ['text' => 'Bekor qilish⛔️'],
                                    ]
                                ],
                                'resize_keyboard' => true,
                                'one_time_keyboard' => true,
                            ];
                            $this->store($admin->chat_id, $userData, $replyMarkup);
                        } else {
                            $this->store($chat_id, "Admin rolidagi foydalanuvchi topilmadi, shu sababdan sizning statusingiz faol emas");
                        }

                        $this->store($chat_id, "Siz muvaffaqiyatli ro'yxatdan o'tdingiz!");

                        Cache::forget("register_step_{$chat_id}");
                        Cache::forget("register_name_{$chat_id}");
                        Cache::forget("register_email_{$chat_id}");
                        Cache::forget("register_password_{$chat_id}");
                        Cache::forget("confirmation_code_{$chat_id}");
                    } else {
                        $this->store($chat_id, "Rasmni yuklab olishda muammo yuz berdi. Iltimos, qaytadan urinib ko'ring.");
                    }
                } else {
                    $this->store($chat_id, "Iltimos, rasm yuboring!");
                }
                return;
            }
            if ($text === 'Tasdiqlash✅') {
                Log::info('keldi');
                $user = User::latest()->first()->update([
                    'status' => '1',
                ]);
                $this->store(User::where('role', 'admin')->first()->chat_id, "Yangi user ro'yxatdan to'liq o'tdi!");
                Log::info($user);
                return;
            }

            if ($text === 'Bekor qilish⛔️') {
                $user = User::latest()->first()->update([
                    'status' => '0',
                ]);
                $this->store(User::where('role', 'admin')->first()->chat_id, "Yangi user to'liq o'tmadi");
                Log::info($user);
                return;
            }

            if ($text === '/profile') {
                $user = User::where('chat_id', $chat_id)->first();

                if ($user) {
                    $profileMessage = "<b>Sizning profilingiz:</b>\n\n" .
                        "<b>Ism:</b> {$user->name}\n" .
                        "<b>Email:</b> {$user->email}";

                    $this->store($chat_id, $profileMessage);

                    if ($user->image) {
                        $filePath = storage_path("app/public/{$user->image}");
                        if (file_exists($filePath)) {
                            Http::attach('photo', file_get_contents($filePath), basename($filePath))
                                ->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendPhoto", [
                                    'chat_id' => $chat_id,
                                ]);
                        } else {
                            $this->store($chat_id, "Profilingiz uchun rasm topilmadi.");
                        }
                    } else {
                        $this->store($chat_id, "Profilingiz uchun rasm yo'q.");
                    }
                } else {
                    $this->store($chat_id, "Profilingiz topilmadi. Iltimos, avval ro'yxatdan o'ting.");
                }
            }
            if ($text === 'Login') {
                Cache::put("login_step_{$chat_id}", 'email');
                $this->store($chat_id, "Iltimos, emailingizni kiriting:");
                return;
            }

            if (Cache::get("login_step_{$chat_id}") === 'email') {
                Cache::put("login_email_{$chat_id}", $text);
                Cache::put("login_step_{$chat_id}", 'password');
                $this->store($chat_id, "Iltimos, parolingizni kiriting:");
                return;
            }

            if (Cache::get("login_step_{$chat_id}") === 'password') {
                Cache::put("login_password_{$chat_id}", $text);

                $email = Cache::get("login_email_{$chat_id}");
                $password = Cache::get("login_password_{$chat_id}");

                $user = User::where('email', $email)->first();

                if ($user && Hash::check($password, $user->password)) {
                    Cache::forget("login_step_{$chat_id}");
                    Cache::forget("login_email_{$chat_id}");
                    Cache::forget("login_password_{$chat_id}");

                    $this->store($chat_id, "Muvaffaqiyatli kirish! Xush kelibsiz, {$user->name}.");
                    $user->update(['chat_id' => $chat_id]);

                    $admin_chat_id = User::where('role', 'admin')->first()->chat_id;

                    $this->store($admin_chat_id, "Iltimos, foydalanuvchilarni tekshiring:", [
                        'keyboard' => [
                            [
                                ['text' => 'Barcha statusi 1 bo\'lgan foydalanuvchilar'],
                                ['text' => 'Barcha statusi 0 bo\'lgan foydalanuvchilar'],
                            ]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true,
                    ]);

                } else {
                    $this->store($chat_id, "Email yoki parol noto'g'ri. Iltimos, qaytadan urinib ko'ring.");
                }
            }

            if ($text === 'Barcha statusi 1 bo\'lgan foydalanuvchilar') {
                $users = User::where('role', '!=', 'admin')->where('status', 1)->get();

                if ($users->isEmpty()) {
                    $this->store($chat_id, "Bunday statusli foydalanuvchilar mavjud emas.");
                } else {
                    $userList = "Barcha statusi 1 bo'lgan foydalanuvchilar:\n";
                    $counter = 1;

                    foreach ($users as $user) {
                        $userList .= "{$counter}) Foydalanuvchi nomi: {$user->name}, Email: {$user->email}\n";
                        $counter++;
                    }

                    $this->store($chat_id, $userList . "\nIltimos, foydalanuvchi raqamini kiriting, uning statusini false qilish uchun.");
                }
            }

            if ($text === 'Barcha statusi 0 bo\'lgan foydalanuvchilar') {
                $users = User::where('role', '!=', 'admin')->where('status', 0)->get();

                if ($users->isEmpty()) {
                    $this->store($chat_id, "Bunday statusli foydalanuvchilar mavjud emas.");
                } else {
                    $userList = "Barcha statusi 0 bo'lgan foydalanuvchilar:\n";
                    $counter = 1;

                    foreach ($users as $user) {
                        $userList .= "{$counter}) Foydalanuvchi nomi: {$user->name}, Email: {$user->email}\n";
                        $counter++;
                    }

                    $this->store($chat_id, $userList . "\nIltimos, foydalanuvchi raqamini kiriting, uning statusini true qilish uchun.");
                }
            }

            if (is_numeric($text)) {
                $userIndex = (int) $text - 1;

                $allUsers = User::where('role', '!=', 'admin')->get();
                if (isset($allUsers[$userIndex])) {
                    $user = $allUsers[$userIndex];

                    $user->status = !$user->status;
                    $user->save();

                    $newStatus = $user->status ? '1' : '0';
                    $this->store($chat_id, "Foydalanuvchining statusi teskari qilindi. Yangi status: {$newStatus}.");
                } else {
                    $this->store($chat_id, "Bunday foydalanuvchi topilmadi. Iltimos, to'g'ri raqamni kiriting.");
                }
            }
        } catch (\Exception $exception) {
            Log::error($exception);
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage()
            ]);
        }
    }
}
