<?php
/**
 * ContractController
 *
 * REST API for the "contracts" collection.
 *
 * Routes (registered in bootstrap.php):
 *   GET    /api/contracts              → index()   – list all contracts
 *   POST   /api/contracts              → store()   – create a contract
 *   GET    /api/contracts/{id}         → show()    – read one contract
 *   PUT    /api/contracts/{id}         → update()  – replace a contract
 *   DELETE /api/contracts/{id}         → destroy() – remove a contract
 *   POST   /api/contracts/preview      → preview() – render HTML without saving
 *   GET    /api/contracts/{id}/preview → showPreview() – render saved contract
 *
 * @package VolunteerContracts\Controller
 */
class ContractController extends BaseController
{
    private StorageInterface $storage;

    public function __construct()
    {
        global $storage;
        $this->storage = $storage;
    }

    // -------------------------------------------------------------------------
    // GET /api/contracts
    // -------------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $contracts = $this->storage->findAll(Contract::COLLECTION);

        // Optionally filter by status
        $status = trim($_GET['status'] ?? '');
        if ($status !== '') {
            $contracts = array_values(array_filter(
                $contracts,
                fn($c) => ($c['status'] ?? '') === $status
            ));
        }

        // Return a summary list
        $list = array_map(fn($c) => [
            'id'                => $c['id'],
            'event_name'        => $c['event_name'],
            'volunteer_name'    => trim(($c['volunteer_first'] ?? '') . ' ' . ($c['volunteer_last'] ?? '')),
            'worker_id'         => $c['worker_id'] ?? null,
            'start_date'        => $c['start_date'],
            'end_date'          => $c['end_date'],
            'total_amount'      => $c['total_amount'],
            'status'            => $c['status'],
            'updated_at'        => $c['updated_at'],
        ], $contracts);

        $this->json(['data' => $list, 'total' => count($list)]);
    }

    // -------------------------------------------------------------------------
    // POST /api/contracts
    // -------------------------------------------------------------------------

    public function store(array $params = []): void
    {
        $input  = $this->input();
        $result = Contract::fromInput($input);

        if (!empty($result['errors'])) {
            $this->error($result['errors']);
        }

        // If a worker_id is supplied, verify it exists
        if (!empty($result['data']['worker_id'])) {
            $this->requireWorker($result['data']['worker_id']);
        }

        $saved = $this->storage->save(Contract::COLLECTION, $result['data']);
        $this->json(['data' => $saved], 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/contracts/{id}
    // -------------------------------------------------------------------------

    public function show(array $params = []): void
    {
        $contract = $this->loadContract($params);
        $this->json(['data' => $contract]);
    }

    // -------------------------------------------------------------------------
    // PUT /api/contracts/{id}
    // -------------------------------------------------------------------------

    public function update(array $params = []): void
    {
        $id = $this->param($params, 'id');

        if (!$this->storage->exists(Contract::COLLECTION, $id)) {
            $this->notFound("Contract '{$id}' not found.");
        }

        $input       = $this->input();
        $input['id'] = $id;

        $result = Contract::fromInput($input);

        if (!empty($result['errors'])) {
            $this->error($result['errors']);
        }

        if (!empty($result['data']['worker_id'])) {
            $this->requireWorker($result['data']['worker_id']);
        }

        $original = $this->storage->find(Contract::COLLECTION, $id);
        $result['data']['created_at'] = $original['created_at'] ?? date('c');

        $saved = $this->storage->save(Contract::COLLECTION, $result['data']);
        $this->json(['data' => $saved]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/contracts/{id}
    // -------------------------------------------------------------------------

    public function destroy(array $params = []): void
    {
        $id = $this->param($params, 'id');

        if (!$this->storage->exists(Contract::COLLECTION, $id)) {
            $this->notFound("Contract '{$id}' not found.");
        }

        $this->storage->delete(Contract::COLLECTION, $id);
        $this->json(['message' => 'Contract deleted.']);
    }

    // -------------------------------------------------------------------------
    // POST /api/contracts/preview   (render without saving)
    // -------------------------------------------------------------------------

    public function preview(array $params = []): void
    {
        $input  = $this->input();
        $result = Contract::fromInput($input);

        // For preview we only abort on hard structural errors, not missing fields
        // (allow partial previews during form filling)
        $worker = null;
        if (!empty($input['worker_id'])) {
            $worker = $this->storage->find(Worker::COLLECTION, $input['worker_id']);
        }

        $vars     = Contract::toTemplateVars($result['data'], $worker);
        $language = $input['language'] ?? 'both';
        $html     = ContractTemplate::render($vars, $language);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['html' => $html, 'vars' => $vars]);
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /api/contracts/{id}/preview
    // -------------------------------------------------------------------------

    public function showPreview(array $params = []): void
    {
        $contract = $this->loadContract($params);

        $worker = null;
        if (!empty($contract['worker_id'])) {
            $worker = $this->storage->find(Worker::COLLECTION, $contract['worker_id']);
        }

        $vars     = Contract::toTemplateVars($contract, $worker);
        $language = $contract['language'] ?? 'both';
        $html     = ContractTemplate::render($vars, $language);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['html' => $html, 'vars' => $vars]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function loadContract(array $params): array
    {
        $id       = $this->param($params, 'id');
        $contract = $this->storage->find(Contract::COLLECTION, $id);

        if ($contract === null) {
            $this->notFound("Contract '{$id}' not found.");
        }

        return $contract;
    }

    private function requireWorker(string $workerId): void
    {
        if (!$this->storage->exists(Worker::COLLECTION, $workerId)) {
            $this->error("Worker '{$workerId}' does not exist.", 422);
        }
    }
}
