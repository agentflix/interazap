================================================================================
GUIDELINE LARAVEL 12+ - PERFORMANCE EXTREMA E BOAS PRÁTICAS
================================================================================

1. # ELOQUENT ORM - OTIMIZAÇÃO DE QUERIES

## N+1 QUERY PROBLEM:

✗ ERRADO:
$users = User::all();
foreach ($users as $user) {
echo $user->profile->bio; // N+1 queries
}

✓ CORRETO:
$users = User::with('profile')->get(); // 2 queries apenas

## EAGER LOADING:

✓ Use with() para relacionamentos que SEMPRE precisará
✓ Use load() para carregar após já ter a model
✓ Use withCount() para contar relacionamentos
✓ Use withExists() apenas para verificar existência
✓ Use withAggregate() para pegar valor específico

## LAZY EAGER LOADING:

$users = User::all();
if ($someCondition) {
$users->load('profile'); // Carrega apenas se necessário
}

## PREVENTING LAZY LOADING:

// Em AppServiceProvider (desenvolvimento)
Model::preventLazyLoading(!app()->isProduction());
// Lança exceção se tentar lazy load

## SELECT ESPECÍFICO:

✗ EVITAR: User::all() // Busca TODAS as colunas
✓ USAR: User::select(['id', 'name', 'email'])->get()
✓ USAR: User::pluck('email') // Apenas uma coluna

## CHUNK PARA GRANDES DATASETS:

✓ User::chunk(1000, function ($users) {
// Processa 1000 por vez
});

✓ User::chunkById(1000, function ($users) {
// Mais seguro, usa ID para paginar
});

✓ User::lazy(1000) // Retorna LazyCollection
->each(function ($user) {
// Processa um por um, memória constante
});

## CURSOR PARA MEMÓRIA EXTREMA:

foreach (User::cursor() as $user) {
// Um registro por vez na memória
}

## EXISTÊNCIA SEM CARREGAR:

✗ EVITAR: if (count($user->posts) > 0)
✓ USAR: if ($user->posts()->exists())
✓ USAR: if ($user->posts()->count() > 0)

## WHERE COM RELACIONAMENTO:

✗ EVITAR: User::with('posts')->get()->filter(fn($u) => $u->posts->count() > 10)
✓ USAR: User::has('posts', '>', 10)->get()
✓ USAR: User::whereHas('posts', fn($q) => $q->where('status', 'published'))->get()

## QUANDO NÃO USAR ELOQUENT:

✓ Queries complexas com múltiplos joins
✓ Bulk operations massivas
✓ Queries de agregação complexas
✓ Relatórios pesados
→ Use Query Builder ou DB::raw()

2. # CACHE - ESTRATÉGIAS AVANÇADAS

## CACHE DRIVERS:

✓ Redis: Melhor para produção (rápido, persistente)
✓ Memcached: Alternativa rápida
✗ File/Database: Apenas desenvolvimento

## CACHE DE QUERIES:

✓ Cache::remember('users.all', 3600, fn() => User::all());

✓ // Cache com tags (Redis/Memcached)
Cache::tags(['users', 'list'])->remember('users.all', 3600, fn() =>
User::all()
);

## INVALIDAÇÃO DE CACHE:

✓ Cache::forget('users.all');
✓ Cache::tags(['users'])->flush(); // Limpa todas tags 'users'

## MODEL CACHING:

✓ Use remember() diretamente em queries:
User::remember(3600)->get();
User::cacheTags(['users'])->remember(3600)->get();

## FRAGMENT CACHING (Views):

@cache('sidebar.recent-posts', now()->addHour())
@foreach($recentPosts as $post)
// ...
@endforeach
@endcache

## CACHE DE CONFIGURAÇÃO:

✓ php artisan config:cache // Produção SEMPRE
✓ php artisan route:cache // Produção SEMPRE
✓ php artisan view:cache // Produção SEMPRE
✗ NUNCA usar env() fora de config files

## CACHE WARMING:

// Schedule para pré-popular cache
$schedule->call(function () {
Cache::remember('dashboard.stats', 3600, fn() =>
// Calcular estatísticas pesadas
);
})->hourly();

## CACHE PROBABILÍSTICO:

// Evita cache stampede
Cache::remember('key', [
'seconds' => 3600,
'early' => 60, // Regenera entre 60-3600s aleatoriamente
], fn() => expensiveOperation());

## CACHE LOCK (Evitar Stampede):

$lock = Cache::lock('process-orders', 10);

if ($lock->get()) {
// Processar
$lock->release();
}

3. # DATABASE - OTIMIZAÇÃO DE QUERIES

## ÍNDICES:

✓ Criar índice em colunas de WHERE frequente
✓ Criar índice em colunas de JOIN
✓ Criar índice em foreign keys
✓ Índice composto para queries com múltiplos WHERE
✗ NÃO indexar colunas com baixa cardinalidade (status, boolean)
✗ NÃO indexar colunas raramente consultadas

## MIGRATIONS:

Schema::table('users', function (Blueprint $table) {
$table->index('email'); // Índice simples
$table->index(['company_id', 'status']); // Índice composto
$table->unique('email'); // Índice único
$table->fullText('description'); // Full-text search
});

## QUERY BUILDER VS RAW:

✓ Query Builder: Maioria dos casos
✓ DB::raw(): Queries complexas específicas do DB
✓ Prepared statements sempre (evita SQL injection)

## BULK OPERATIONS:

✗ EVITAR loops com save():
foreach ($data as $item) {
    User::create($item); // N queries
}

✓ USAR bulk insert:
User::insert($data); // 1 query

✓ USAR upsert (update or insert):
User::upsert($data, ['email'], ['name', 'updated_at']);

## UPDATE EM MASSA:

✗ EVITAR: User::all()->each(fn($u) => $u->update(['status' => 'active']))
✓ USAR: User::where('status', 'pending')->update(['status' => 'active'])

## TRANSACTIONS:

DB::transaction(function () {
// Operações atômicas
$user = User::create($data);
$user->profile()->create($profileData);
}); // Auto rollback em exceção

// Controle manual
DB::beginTransaction();
try {
// operações
DB::commit();
} catch (\Exception $e) {
DB::rollBack();
throw $e;
}

## EXPLAIN QUERIES:

// Analisar performance
DB::listen(function ($query) {
    Log::info($query->sql);
Log::info($query->bindings);
    Log::info($query->time);
});

// Debugbar em desenvolvimento
composer require barryvdh/laravel-debugbar --dev

4. # JOBS E QUEUES - PROCESSAMENTO ASSÍNCRONO

## QUANDO USAR QUEUES:

✓ Envio de emails
✓ Processamento de imagens
✓ Exportação de relatórios
✓ Integrações com APIs externas
✓ Notificações
✓ Qualquer operação > 500ms

## QUEUE DRIVERS:

✓ Redis: Melhor para produção
✓ Database: Desenvolvimento/pequeno volume
✓ SQS: AWS
✗ Sync: Apenas testes

## CONFIGURAÇÃO:

// .env
QUEUE_CONNECTION=redis

// config/queue.php
'redis' => [
'driver' => 'redis',
'connection' => 'default',
'queue' => env('REDIS_QUEUE', 'default'),
'retry_after' => 90,
'block_for' => null,
]

## JOB STRUCTURE:

class ProcessOrder implements ShouldQueue
{
use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Tentativas
    public $timeout = 120; // Timeout em segundos
    public $backoff = [10, 30, 60]; // Backoff entre tentativas

    public function handle()
    {
        // Lógica do job
    }

    public function failed(Throwable $exception)
    {
        // Lógica quando falha após todas tentativas
    }

}

## DISPATCH:

// Síncrono (NÃO usar em produção)
ProcessOrder::dispatchSync($order);

// Assíncrono
ProcessOrder::dispatch($order);

// Com delay
ProcessOrder::dispatch($order)->delay(now()->addMinutes(10));

// Fila específica
ProcessOrder::dispatch($order)->onQueue('high-priority');

// Conexão específica
ProcessOrder::dispatch($order)->onConnection('sqs');

// Chain de jobs (sequencial)
Bus::chain([
new ProcessOrder($order),
new SendNotification($order),
new UpdateAnalytics($order),
])->dispatch();

// Batch de jobs (paralelo)
Bus::batch([
new ProcessOrder($order1),
new ProcessOrder($order2),
new ProcessOrder($order3),
])->dispatch();

## PRIORIZAÇÃO:

// Múltiplos workers com prioridade
php artisan queue:work --queue=high,default,low

## RATE LIMITING:

use Illuminate\Queue\Middleware\RateLimited;

public function middleware()
{
return [new RateLimited('api-calls')];
}

// Em AppServiceProvider
RateLimiter::for('api-calls', function ($job) {
return Limit::perMinute(10);
});

## MONITORING:

// Horizon (Redis) - ALTAMENTE RECOMENDADO
composer require laravel/horizon
php artisan horizon:install
php artisan horizon

// Metrics no dashboard
// Processos falhados
// Throughput
// Tempo de processamento

5. # EVENTOS E LISTENERS - DESACOPLAMENTO

## QUANDO USAR:

✓ Ações que podem ser assíncronas
✓ Notificações
✓ Logging
✓ Atualizações de cache
✓ Integrações com sistemas externos

## ESTRUTURA:

// Event
class OrderShipped
{
public function \_\_construct(public Order $order) {}
}

// Listener
class SendShipmentNotification implements ShouldQueue
{
public function handle(OrderShipped $event)
{
// Enviar notificação
}
}

// Registrar em EventServiceProvider
protected $listen = [
OrderShipped::class => [
SendShipmentNotification::class,
UpdateInventory::class,
LogShipment::class,
],
];

## DISPATCH:

event(new OrderShipped($order));
OrderShipped::dispatch($order);

## LISTENERS ASSÍNCRONOS:

class SendShipmentNotification implements ShouldQueue
{
public $queue = 'notifications';
public $tries = 3;
public $timeout = 30;
}

## EVENT SUBSCRIBERS:

// Para múltiplos eventos relacionados
class UserEventSubscriber
{
public function handleUserLogin($event) {}
    public function handleUserLogout($event) {}

    public function subscribe($events)
    {
        $events->listen(UserLogin::class, [UserEventSubscriber::class, 'handleUserLogin']);
        $events->listen(UserLogout::class, [UserEventSubscriber::class, 'handleUserLogout']);
    }

}

6. # HTTP CLIENT - REQUESTS EXTERNOS

## BÁSICO:

use Illuminate\Support\Facades\Http;

$response = Http::get('https://api.example.com/users');
$response = Http::post('https://api.example.com/users', ['name' => 'John']);

## TIMEOUT:

✓ Http::timeout(3)->get($url); // 3 segundos
✓ Http::connectTimeout(2)->timeout(10)->get($url);

## RETRY:

✓ Http::retry(3, 100)->get($url); // 3 tentativas, 100ms entre elas
✓ Http::retry(3, 100, throw: false)->get($url); // Não lança exceção

## CONCORRENTE (ASSÍNCRONO):

$responses = Http::pool(fn ($pool) => [
$pool->get('https://api1.com/data'),
$pool->get('https://api2.com/data'),
$pool->get('https://api3.com/data'),
]);

// Acesso
$responses[0]->json();
$responses[1]->json();

## MIDDLEWARE:

Http::withMiddleware(
Middleware::mapRequest(function ($request) {
        return $request->withHeader('X-Custom', 'value');
    })
)->get($url);

## CACHE DE RESPONSES:

$response = Cache::remember('api.users', 3600, fn() =>
Http::get('https://api.example.com/users')->json()
);

## CIRCUIT BREAKER PATTERN:

// Implementar manualmente ou usar pacote
if (Cache::has('api.down')) {
return []; // API está down, retorna fallback
}

try {
$response = Http::timeout(2)->get($url);
} catch (\Exception $e) {
Cache::put('api.down', true, 300); // 5 minutos
return []; // Fallback
}

## FAKE PARA TESTES:

Http::fake([
'api.example.com/\*' => Http::response(['data' => 'test'], 200),
]);

7. # RESPONSE CACHING - HTTP CACHE

## ETAG:

return response()->json($data)
    ->setEtag(md5($data))
->setPublic()
->setMaxAge(3600);

// Laravel compara automaticamente If-None-Match

## LAST-MODIFIED:

$lastModified = $post->updated_at;

if ($request->header('If-Modified-Since') == $lastModified) {
return response()->noContent(304);
}

return response()->json($post)
->header('Last-Modified', $lastModified);

## CACHE-CONTROL:

return response()->json($data)
->header('Cache-Control', 'public, max-age=3600');

## RESPONSE CACHING MIDDLEWARE:

composer require spatie/laravel-responsecache

Route::middleware('cacheResponse:3600')->get('/api/posts', ...);

8. # VALIDATION - PERFORMANCE E BOAS PRÁTICAS

## FORM REQUEST:

✓ Sempre usar FormRequest ao invés de validar no controller
✓ Reutilizável e testável

class StoreUserRequest extends FormRequest
{
public function rules()
{
return [
'email' => ['required', 'email', 'unique:users'],
'name' => ['required', 'string', 'max:255'],
];
}
}

## SOMETIMES:

// Valida apenas se presente
'email' => ['sometimes', 'required', 'email']

## BAIL:

// Para na primeira falha (performance)
'email' => ['bail', 'required', 'email', 'unique:users']

## CUSTOM RULES:

// Rule object (reutilizável)
class Uppercase implements Rule
{
public function passes($attribute, $value)
    {
        return strtoupper($value) === $value;
}
}

'name' => ['required', new Uppercase]

## DATABASE VALIDATION:

✗ EVITAR muitas validações unique/exists em loop
✓ Validar em batch quando possível
✓ Usar cache para exists frequentes

// Cache de validação exists
Rule::exists('companies', 'id')->where(function ($query) {
return Cache::remember('companies.ids', 3600, fn() =>
$query->pluck('id')
);
});

9. # API RESOURCES - TRANSFORMAÇÃO DE DADOS

## QUANDO USAR:

✓ APIs públicas
✓ Transformação consistente
✓ Ocultar campos sensíveis
✓ Incluir relacionamentos opcionais

## RESOURCE:

class UserResource extends JsonResource
{
public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'posts' => PostResource::collection($this->whenLoaded('posts')),
'is_admin' => $this->when($request->user()->isAdmin(), true),
];
}
}

// Uso
return UserResource::collection(User::with('posts')->get());
return new UserResource($user);

## CONDITIONAL ATTRIBUTES:

'secret' => $this->when($request->user()->isAdmin(), $this->secret),
'posts' => PostResource::collection($this->whenLoaded('posts')),
'posts_count' => $this->when($this->relationLoaded('posts'), $this->posts->count()),

## RESOURCE COLLECTION:

class UserCollection extends ResourceCollection
{
public function toArray($request)
{
return [
'data' => $this->collection,
'meta' => [
'total' => $this->total(),
],
];
}
}

10. # MIDDLEWARE - OTIMIZAÇÃO E ORGANIZAÇÃO

## TERMINABLE MIDDLEWARE:

// Executa APÓS response enviada
class PerformanceMonitor
{
public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $request->start_time = $start;
        return $next($request);
}

    public function terminate($request, $response)
    {
        $duration = microtime(true) - $request->start_time;
        Log::info("Request took {$duration}s");
    }

}

## CACHE MIDDLEWARE:

public function handle($request, Closure $next)
{
$key = 'route:' . $request->fullUrl();

    if (Cache::has($key)) {
        return Cache::get($key);
    }

    $response = $next($request);

    Cache::put($key, $response, 3600);

    return $response;

}

## THROTTLING AVANÇADO:

// Em RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(60)->by($request->user()->id)
: Limit::perMinute(10)->by($request->ip());
});

// Uso
Route::middleware('throttle:api')->group(...);

## CONDITIONAL MIDDLEWARE:

public function handle($request, Closure $next)
{
    if ($this->shouldProcess($request)) {
// Processar
}

    return $next($request);

}

11. # ELOQUENT RELATIONSHIPS - PERFORMANCE

## INVERSE RELATIONSHIP:

// Sempre definir relacionamento inverso
class Post extends Model
{
public function user()
{
return $this->belongsTo(User::class);
}
}

class User extends Model
{
public function posts()
{
return $this->hasMany(Post::class);
}
}

## RELATIONSHIP CACHING:

// No model
protected $with = ['user']; // Sempre eager load

// Ou condicionalmente
public function scopeWithUser($query)
{
return $query->with('user');
}

## RELATIONSHIP COUNTS:

✗ EVITAR: $user->posts->count() // Carrega todos posts
✓ USAR: $user->posts()->count() // Query de count apenas
✓ USAR: $user->loadCount('posts'); $user->posts_count

## RELATIONSHIP EXISTS:

✗ EVITAR: $user->posts->isNotEmpty()
✓ USAR: $user->posts()->exists()

## POLIMÓRFICO OTIMIZADO:

// Eager load polimórfico
Comment::with('commentable')->get();

// Melhor: morphMap
Relation::morphMap([
'post' => Post::class,
'video' => Video::class,
]);

12. # COLLECTIONS - OPERAÇÕES EFICIENTES

## LAZY COLLECTIONS:

// Para grandes datasets
User::cursor() // LazyCollection
->filter(fn($user) => $user->isActive())
    ->map(fn($user) => $user->email)
    ->each(fn($email) => Mail::to($email)->send(...));

## EVITAR OPERAÇÕES PESADAS:

✗ EVITAR:
$collection->map(function ($item) {
return User::find($item->user_id); // N queries
});

✓ USAR:
$userIds = $collection->pluck('user_id');
$users = User::whereIn('id', $userIds)->get()->keyBy('id');
$collection->map(fn($item) => $users[$item->user_id]);

## CHUNK:

User::chunk(1000, function ($users) {
    $users->each(function ($user) {
// Processar
});
});

## PLUCK VS MAP:

✗ EVITAR: $users->map(fn($u) => $u->email)
✓ USAR: $users->pluck('email')

13. # SESSIONS E COOKIES - OTIMIZAÇÃO

## SESSION DRIVER:

✓ Redis: Melhor para múltiplos servidores
✓ Database: Alternativa persistente
✗ File: Apenas desenvolvimento único servidor

## SESSION CONFIGURATION:

// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'lifetime' => 120,
'expire_on_close' => false,

## FLASH DATA:

// Dados que existem apenas na próxima request
session()->flash('status', 'Success!');
return redirect()->with('status', 'Success!');

## SESSION REGENERATION:

// Após login (segurança)
$request->session()->regenerate();

## COOKIES:

// Cookie seguro
return response()->json($data)->cookie(
'name', 'value',
$minutes = 60,
$path = '/',
$domain = null,
$secure = true,
$httpOnly = true,
$sameSite = 'strict'
);

14. # LOGGING - PERFORMANCE E DEBUGGING

## LOG CHANNELS:

// config/logging.php
'channels' => [
'stack' => [
'driver' => 'stack',
'channels' => ['daily', 'slack'],
],
'daily' => [
'driver' => 'daily',
'path' => storage_path('logs/laravel.log'),
'level' => 'debug',
'days' => 14,
],
]

## CONTEXT:

Log::info('User login', [
'user_id' => $user->id,
'ip' => $request->ip(),
]);

## CONDITIONAL LOGGING:

// Apenas em desenvolvimento
if (app()->environment('local')) {
Log::debug('Query executed', ['sql' => $sql]);
}

## SLOW QUERY LOGGING:

// Em AppServiceProvider
DB::listen(function ($query) {
    if ($query->time > 1000) { // > 1 segundo
Log::warning('Slow query detected', [
'sql' => $query->sql,
'time' => $query->time,
'bindings' => $query->bindings,
]);
}
});

## CUSTOM LOG CHANNEL:

Log::channel('slack')->error('Something went wrong!');

15. # FILE STORAGE - OTIMIZAÇÃO

## FILESYSTEM DRIVERS:

✓ S3: Produção (escalável, CDN)
✓ Local: Desenvolvimento
✓ FTP/SFTP: Legado

## STREAMING:

// Para arquivos grandes
return Storage::download('file.pdf');
return Storage::response('file.pdf'); // Stream no browser

## VISIBILITY:

// Público (acessível via URL)
Storage::disk('s3')->put('file.jpg', $contents, 'public');

// Privado (requer signed URL)
Storage::disk('s3')->put('file.jpg', $contents, 'private');

## SIGNED URLS:

// URL temporária para arquivo privado
$url = Storage::temporaryUrl('file.jpg', now()->addMinutes(5));

## CHUNKED UPLOAD:

// Para arquivos muito grandes
$stream = fopen('large-file.zip', 'r');
Storage::put('large-file.zip', $stream);
fclose($stream);

## CDN INTEGRATION:

// config/filesystems.php
's3' => [
'driver' => 's3',
'url' => env('AWS_CDN_URL'), // CloudFront URL
]

16. # RATE LIMITING - PROTEÇÃO E PERFORMANCE

## API RATE LIMITING:

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Múltiplos limites
RateLimiter::for('uploads', function (Request $request) {
    return [
        Limit::perMinute(10),
        Limit::perHour(100)->by($request->user()->id),
];
});

## RESPONSE COM HEADERS:

// Laravel adiciona automaticamente
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 60

## CUSTOM RATE LIMITERS:

RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)
        ->by($request->input('email'))
->response(function () {
return response('Too many login attempts.', 429);
});
});

// Uso
Route::middleware('throttle:login')->post('/login', ...);

17. # BROADCASTING - REAL-TIME OTIMIZADO

## DRIVERS:

✓ Pusher: Mais fácil, pago
✓ Redis + Laravel Echo: Open source, mais controle
✓ Ably: Alternativa paga

## QUEUE BROADCASTS:

class OrderShipped implements ShouldBroadcast
{
use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn()
    {
        return new PrivateChannel('orders.' . $this->order->id);
    }

    public function broadcastWith()
    {
        return ['order_id' => $this->order->id]; // Apenas dados necessários
    }

    public function broadcastQueue()
    {
        return 'broadcasts'; // Fila específica
    }

}

## PRESENCE CHANNELS:

// Para "online users"
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
return ['id' => $user->id, 'name' => $user->name];
});

## OPTIMIZAÇÃO:

✓ Broadcast apenas dados mínimos
✓ Use private/presence channels quando apropriado
✓ Queue broadcasts sempre
✓ Considerar debounce no frontend

18. # TESTING - PERFORMANCE E COBERTURA

## DATABASE:

// Use banco em memória
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>

## FACTORIES:

// Usar factories ao invés de criar manualmente
User::factory()->count(10)->create();
Post::factory()->for($user)->create();

## REFRESH DATABASE:

use RefreshDatabase; // Mais rápido que DatabaseMigrations

## MOCKING:

// Mock services externos
Http::fake([
'api.example.com/\*' => Http::response(['data' => 'test'], 200),
]);

// Mock jobs
Queue::fake();
ProcessOrder::assertPushed();

// Mock events
Event::fake();
OrderShipped::assertDispatched();

## PARALLEL TESTING:

php artisan test --parallel

## TIME ASSERTIONS:

$this->travelTo(now()->addDay());
$this->travel(5)->days();

## PERFORMANCE TESTS:

// Medir tempo
$this->assertLessThan(1000, function () {
User::all();
});

19. # SECURITY - PROTEÇÕES ESSENCIAIS

## MASS ASSIGNMENT:

✓ Sempre definir $fillable ou $guarded
protected $fillable = ['name', 'email'];
protected $guarded = ['id', 'is_admin'];

## SQL INJECTION:

✗ DB::raw("SELECT _ FROM users WHERE id = " . $id)
✓ DB::select('SELECT _ FROM users WHERE id = ?', [$id])
✓ User::where('id', $id)->first() // Eloquent protege

## XSS:

✓ Blade escapa automaticamente: {{ $var }}
✗ {!! $var !!} // Apenas se confiável
✓ Use @csrf em forms
✓ Sanitize input: strip_tags(), htmlspecialchars()

## CSRF:

✓ @csrf em todos forms POST/PUT/DELETE
✓ Verificação automática de token

## HEADERS DE SEGURANÇA:

// Em middleware
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-XSS-Protection', '1; mode=block');
$response->headers->set('Strict-Transport-Security', 'max-age=31536000');

## AUTHORIZATION:

✓ Policies para autorização
✓ Gates para permissões simples
✗ NUNCA confiar apenas em frontend

// Policy
public function update(User $user, Post $post)
{
return $user->id === $post->user_id;
}

// Uso
$this->authorize('update', $post);

## ENCRYPTION:

$encrypted = encrypt('secret');
$decrypted = decrypt($encrypted);

// Para dados sensíveis no DB
protected $casts = [
'secret' => 'encrypted',
];

20. # DEPLOYMENT - CHECKLIST DE PERFORMANCE

## ANTES DE DEPLOY:

✓ php artisan config:cache
✓ php artisan route:cache
✓ php artisan view:cache
✓ php artisan event:cache
✓ composer install --optimize-autoloader --no-dev
✓ npm run build

## OTIMIZAÇÕES:

✓ OPcache habilitado
✓ Redis para cache e sessions
✓ Queue workers rodando (Supervisor)

# ANTI-PATTERNS - O QUE EVITAR

✗ Lógica de negócio no Controller
→ Mover para Services/Actions
✗ Queries no loop
→ Eager loading ou chunk
✗ env() fora de config files
→ Sempre usar config()
✗ DB::raw sem preparação
→ Usar bindings
✗ Eloquent para operações massivas
→ Usar Query Builder ou raw SQL
✗ Não fazer cache de configuração
→ php artisan config:cache
✗ Não usar queues para operações lentas
→ Dispatch jobs
✗ Session em arquivo com múltiplos servidores
→ Usar Redis/Database
✗ Logs excessivos em produção
→ Log apenas o necessário
✗ Não monitorar performance
→ Usar APM e profiling
✗ Validação apenas no frontend
→ Sempre validar no backend
✗ Não usar índices no banco
→ Indexar colunas consultadas

# CHECKLIST DIÁRIO DE PERFORMANCE

DESENVOLVIMENTO:
☐ Queries com eager loading?
☐ Índices necessários criados?
☐ Validação no FormRequest?
☐ Jobs para operações > 500ms?
☐ Cache onde apropriado?
☐ Lazy Collections para grandes datasets?
☐ Bulk operations ao invés de loops?
☐ Authorization implementada?
CODE REVIEW:
☐ N+1 queries eliminados?
☐ Queries otimizadas?
☐ Cache invalidation correta?
☐ Jobs com retry e timeout?
☐ Rate limiting em APIs?
☐ Security best practices?
☐ Testes cobrem casos críticos?
PRODUÇÃO:
☐ Caches gerados (config, route, view)?
☐ Autoloader otimizado?
☐ Queue workers rodando?
☐ Monitoring configurado?
☐ Logs sendo coletados?
☐ Backups automatizados?
☐ HTTPS habilitado?
☐ Headers de segurança configurados?
================================================================================
FIM DO GUIDELINE - LARAVEL 12+ PERFORMANCE EXTREMA
RESUMO RÁPIDO - GOLDEN RULES:

SEMPRE eager load relacionamentos
Use cache agressivamente (Redis)
Queue tudo que demora > 500ms
Índices no banco para WHERE/JOIN
Bulk operations, NUNCA loops
FormRequest para validação
API Resources para transformação
Events para desacoplamento
Lazy Collections para grandes datasets
Config/Route/View cache em produção

PERFORMANCE TARGETS:

API response: < 200ms
Page load: < 1s
Database query: < 100ms
Queue job: < 30s

NUNCA:

env() fora de config
Lógica no Controller
Queries em loop
DB::raw sem bindings
Session em file (produção)
Eloquent para bulk operations
Validação só no frontend

================================================================================
