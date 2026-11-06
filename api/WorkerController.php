<?php
/**
 * WorkerController
 *
 * REST API for the "workers" collection.
 *
 * Routes (registered in bootstrap.php):
 *   GET    /api/workers          → index()   – list all workers
 *   POST   /api/workers          → store()   – create a worker
 *   GET    /api/workers/{id}     → show()    – read one worker
 *   PUT    /api/workers/{id}     → update()  – replace a worker
 *   DELETE /api/workers/{id}     → destroy() – remove a worker
 *
 * @package VolunteerContracts\Controller
 */
class WorkerController extends BaseController
{
    private StorageInterface $storage;

    public function __construct()
    {
        // Injected via the bootstrap; kept simple for now.
        global $storage;
        $this->storage = $storage;
    }

    // -------------------------------------------------------------------------
    // GET /api/workers
    // -------------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $search = strtolower(trim($_GET['q'] ?? ''));
        $workers = $this->storage->findAll(Worker::COLLECTION);
        $workers = $this->storage->findAll(Worker::COLLECTION);

        usort(
            $workers,
            fn($a, $b) =>
                strcmp(
                    strtolower(($a['first_name'] ?? '') . ($a['last_name'] ?? '')),
                    strtolower(($b['first_name'] ?? '') . ($b['last_name'] ?? ''))
                )
        );

        if ($search !== '') {
            $workers = array_values(array_filter($workers, function (array $w) use ($search): bool {
                $haystack = strtolower(
                    ($w['first_name'] ?? '') . ' ' .
                    ($w['last_name'] ?? '') . ' ' .
                    ($w['email'] ?? '')
                );
                return str_contains($haystack, $search);
            }));
        }

        // Return a lightweight list (no IBAN in list view for privacy)
        $list = array_map(fn($w) => [
            'id' => $w['id'],
            'first_name' => $w['first_name'],
            'last_name' => $w['last_name'],
            'email' => $w['email'],
            'city' => $w['city'] ?? '',
            'updated_at' => $w['updated_at'],
        ], $workers);

        $this->json(['data' => $list, 'total' => count($list)]);
    }

    // -------------------------------------------------------------------------
    // POST /api/workers
    // -------------------------------------------------------------------------

    public function store(array $params = []): void
    {
        $result = Worker::fromInput($this->input());

        if (!empty($result['errors'])) {
            $this->error($result['errors']);
        }

        $saved = $this->storage->save(Worker::COLLECTION, $result['data']);
        $this->json(['data' => $saved], 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/workers/{id}
    // -------------------------------------------------------------------------

    public function show(array $params = []): void
    {
        $id = $this->param($params, 'id');
        $worker = $this->storage->find(Worker::COLLECTION, $id);

        if ($worker === null) {
            $this->notFound("Worker '{$id}' not found.");
        }

        $this->json(['data' => $worker]);
    }

    // -------------------------------------------------------------------------
    // PUT /api/workers/{id}
    // -------------------------------------------------------------------------

    public function update(array $params = []): void
    {
        $id = $this->param($params, 'id');

        if (!$this->storage->exists(Worker::COLLECTION, $id)) {
            $this->notFound("Worker '{$id}' not found.");
        }

        $input = $this->input();
        $input['id'] = $id; // ensure the id is preserved

        $result = Worker::fromInput($input);

        if (!empty($result['errors'])) {
            $this->error($result['errors']);
        }

        // Preserve original created_at
        $original = $this->storage->find(Worker::COLLECTION, $id);
        $result['data']['created_at'] = $original['created_at'] ?? date('c');

        $saved = $this->storage->save(Worker::COLLECTION, $result['data']);
        $this->json(['data' => $saved]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/workers/{id}
    // -------------------------------------------------------------------------

    public function destroy(array $params = []): void
    {
        $id = $this->param($params, 'id');

        if (!$this->storage->exists(Worker::COLLECTION, $id)) {
            $this->notFound("Worker '{$id}' not found.");
        }

        $this->storage->delete(Worker::COLLECTION, $id);
        $this->json(['message' => 'Worker deleted.']);
    }
}
