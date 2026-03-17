<?php

namespace App\Models;

use Auth;
use Storage;
use App\Models\Wallet;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Inbound;
use App\Models\BabelFee;
use App\Models\InputOutput;
use App\Models\Transaction;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'avatar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the URL of the user's profile photo.
     *
     * @return string|null
     */
    public function getAvatarAttribute(): ?string
    {
        if ($this->profile_photo_path) {
           return Storage::url($this->profile_photo_path);
        }

        return null;
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function babelfees(): HasMany
    {
        return $this->hasMany(BabelFee::class);
    }

    public function inbounds(): HasMany
    {
        return $this->hasMany(Inbound::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function input_outputs(): HasMany
    {
        return $this->hasMany(InputOutput::class);
    }

    /**
     * Create a custody wallet for the user.
     * 
     * * @return bool
     */
    public function createWallet(): bool
    {        
        if (empty(Wallet::where('user_id', $this->id)->first())) {
            $directory = '/tmp/'.$this->id;

            $cmd = 'cardano-cli address key-gen --verification-key-file '.$directory.'.vkey --signing-key-file '.$directory.'.skey';

            $ret = 0;

            $output = [];

            if (exec($cmd, $output, $ret) !== false) {
                $cmd = 'cardano-cli address build --payment-verification-key-file '.$directory.'.vkey --out-file '.$directory.'.addr --mainnet';

                $ret = 0;

                $output = [];

                if (exec($cmd, $output, $ret) !== false) {
                    $wallet = new Wallet;

                    $wallet->user_id = $this->id;

                    $wallet->address = trim(file_get_contents($directory.'.addr'));

                    $wallet->verification_key = file_get_contents($directory.'.vkey');
                    $wallet->signing_key = file_get_contents($directory.'.skey');

                    $wallet->hash = hash('sha256', trim($wallet->address.$wallet->verification_key.$wallet->signing_key));

                    $wallet->policy_id = $this->createPolicy($directory);

                    $wallet->policy_script = file_get_contents($directory.'.policy.script');

                    // Activation only after successful email verificytion

                    $wallet->is_active = false;
                    
                    if (!empty($wallet->address) && !empty($wallet->policy_id) && !empty($wallet->signing_key) && !empty($wallet->verification_key) && $wallet->save()) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Create a custody wallet for the user.
     * 
     * * @return bool
     */
    public function createPolicy(string $directory): ?string
    {
        $cmd = 'cardano-cli address key-hash --payment-verification-key-file '.$directory.'.vkey';
            
        $ret = 0;

        $output = [];

        if (exec($cmd, $output, $ret) !== false) {

            if (!empty($output) && !empty($output[0])) {
                file_put_contents($directory.'.policy.script', '{'.PHP_EOL);
                file_put_contents($directory.'.policy.script', '    "type": "sig",'.PHP_EOL, FILE_APPEND);
                file_put_contents($directory.'.policy.script', '    "keyHash": "'.$output[0].'"'.PHP_EOL, FILE_APPEND);
                file_put_contents($directory.'.policy.script', '}', FILE_APPEND);  

                $cmd = 'cardano-cli conway transaction policyid --script-file '.$directory.'.policy.script';
            
                $ret = 0;

                $output = [];

                if (exec($cmd, $output, $ret) !== false) {

                    if (!empty($output) && !empty($output[0])) {
                        return $output[0];
                    }
                }
            }
        }
        
        return null;
    }

}
