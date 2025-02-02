<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TypeaheadElandIntersystemAccountsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-eland-intersystem-accounts/{remote_schema}/{thumbprint}',
        name: 'typeahead_eland_intersystem_accounts',
        methods: ['GET'],
        requirements: [
            'remote_schema' => '%assert.schema%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        string $remote_schema,
        string $thumbprint,
        Db $db,
        LoggerInterface $logger,
        IntersystemsService $intersystems_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $eland_intersystems = $intersystems_service->get_eland($pp->schema());

        if (!isset($eland_intersystems[$remote_schema]))
        {
            $logger->debug('typeahead/eland_intersystem_accounts: ' .
                $remote_schema . ' not valid',
                ['schema' => $pp->schema()]);

            return $this->json([], 404);
        }

        $params = [
            'remote_schema' => $remote_schema,
        ];

        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, $params);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $fetched_users = $db->fetchAllAssociative(
            'select code as c,
                name as n,
                extract(epoch from adate) as a,
                status as s
            from ' . $remote_schema . '.users
            where status in (1, 2)
            order by id asc', [], []
        );

        $accounts = [];

        foreach ($fetched_users as $account)
        {
            $accounts[] = $account;
        }

        $data = json_encode($accounts);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, $params);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
