# API de Controle de Estoque e Vendas

Projeto backend em Laravel que implementa endpoints para gerenciamento de produtos, inventário e vendas, com processamento assíncrono, cache e observability mínima.

## Sumário

- Quickstart (Docker)
- Desenvolvimento local
- Testes
- Fila & Scheduler
- Observability
- Otimizações implementadas
- Para avaliadores
- Troubleshooting
- Contribuição

## Requisitos

- PHP 8.1+
- Composer
- Docker & Docker Compose (recomendado)
- SQLite/MySQL (usado em testes e produção)

## Quickstart (Docker)

1. Copie o arquivo de ambiente e ajuste as variáveis se necessário:

```bash
cp backend/.env.example backend/.env
```

2. Subir os serviços com Docker Compose:

```bash
docker-compose up -d --build
```

3. Executar migrations e seeders (dentro do container `backend`):

```bash
docker compose exec backend php artisan migrate --seed
```

4. A API ficará exposta conforme configuração do `docker-compose.yml` (verifique a porta configurada).

## Desenvolvimento local

Instalação e execução local:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

## Testes

Executar toda a suíte PHPUnit:

```bash
cd backend
./vendor/bin/phpunit
```

Executar apenas os testes de integração mais relevantes:

```bash
./vendor/bin/phpunit tests/Feature/Integration/SaleFlowIntegrationTest.php \
  tests/Feature/Integration/ConcurrentSalesTest.php \
  tests/Feature/Integration/IdempotentRetryJobTest.php
```

## Fila & Scheduler

Instruções rápidas para trabalhar com filas e scheduler.

Local (sem Docker):

```bash
# Inicia um worker para processar filas (use a conexão definida em QUEUE_CONNECTION)
cd backend
php artisan queue:work --tries=3 --sleep=3 --queue=default,inventory,sales

# Para rodar o agendador manualmente (útil para desenvolvimento)
php artisan schedule:run
```

Com Docker Compose (container `backend` já configurado):

```bash
# Execute um worker dentro do container
docker compose exec backend php artisan queue:work --tries=3 --sleep=3 --queue=default,inventory,sales

# Rodar o scheduler uma vez (cronagem real deve executar `php artisan schedule:run` a cada minuto)
docker compose exec backend php artisan schedule:run
```

Observações:

- O projeto usa Redis como driver de fila/cache por padrão (ver `backend/.env.example`).
- Use Horizon (se configurado) para observar filas em produção/local.
- Certifique-se de executar `php artisan migrate --seed` antes de processar filas que dependam de dados.
 - O projeto usa Redis como driver de fila/cache por padrão (ver `backend/.env.example`).
 - Use Horizon (se configurado) para observar filas em produção/local.
 - Certifique-se de executar `php artisan migrate --seed` antes de processar filas que dependam de dados.

## Estrutura principal

- `app/` - Código da aplicação (Domain, Application, Infrastructure)
- `routes/` - Rotas da aplicação
- `database/` - Migrations, factories e seeders
- `tests/` - Testes automatizados

## Observability

- Endpoint prometheus-style: `GET /api/v1/observability/metrics` — expõe métricas simples armazenadas em cache.
- Proteção: por padrão o endpoint está protegido por IP (ver `config/observability.php` > `allowed_ips`). Em dev deixe vazio.
- Para produção: proteja via firewall/ACL e exporte métricas para Prometheus.

### Como habilitar scraping local

1. Garanta que `OBS_ALLOWED_IPS` inclua o IP do seu Prometheus (ou deixe vazio para dev).
2. Configure Prometheus para raspar `http://<host>/api/v1/observability/metrics`.

## Próximos passos recomendados

- Adicionar CI (GitHub Actions) que execute `composer install`, `pint` e `phpunit`.
- Integrar `MetricsCollector` com um backend real (Prometheus Pushgateway ou exporter).
- Configurar Sentry para captura de exceções em jobs/queues.

## Otimizações implementadas

Esta seção descreve as decisões de arquitetura e otimizações aplicadas no código para garantir performance, consistência e observabilidade.

- Decremento atômico no banco de dados
	- `InventoryRepository::decrementIfEnough` utiliza um `UPDATE ... WHERE quantity >= ?` atômico para evitar double-decrement em cenários concorrentes sem precisar de locks pesados.

- Locks por produto (opcional)
	- `InventoryLockService` fornece uma abstração de lock distribuído (Redis lock) usada pelo `UpdateInventoryJob` para serializar atualizações por produto quando necessário.

- Transações e idempotência
	- `UpdateInventoryJob` executa operações de decremento dentro de uma transação (`Transactions::run`) garantindo rollback em caso de falhas; testes de idempotência/ retry cobrem esse comportamento.

- Cache com Versioning para listagens
	- `InventoryCache` armazena itens individuais e listas; para invalidar listas ao atualizar qualquer produto, a estratégia é `bumpListVersion()` — incrementa uma chave `inventory:list_version`, tornando chaves de listagem antigas obsoletas sem precisar deletar múltiplas chaves.

- TTLs configuráveis
	- TTLs de item e versão são configuráveis via `config/inventory.php` (`item_ttl`, `version_ttl`) para ajustar trade-offs entre frescor e carga no banco.

- Filas e processamento assíncrono
	- Criação de vendas enfileira o processamento de inventário; isso desacopla latência da API do trabalho custoso e melhora throughput.

- Observabilidade mínima
	- Métricas básicas (`MetricsCollector`) e exposição via `/api/v1/observability/metrics` permitem monitorar contadores críticos (jobs start/completed/failure, item.decrement, cache invalidations).

- Testes de concorrência e integração
	- Testes automatizados cobrem cenários concorrentes, rollback e idempotência garantindo que as otimizações funcionem sob carga simulada.

Essas otimizações priorizam segurança de dados (consistência) e facilidade operacional. Para cenários de altíssima escala, recomenda-se migrar o `MetricsCollector` para um backend de métricas real (Prometheus, InfluxDB) e usar filas/consumers horizontalizados (Horizon, supervisord) com monitoramento de latência e retries.

## Para avaliadores

Seção curta com passos práticos que o avaliador pode seguir para verificar requisitos do teste:

1. Rodar migrations e seed:

```bash
docker compose exec backend php artisan migrate --seed
```

2. Criar uma venda via API (exemplo):

```bash
curl -X POST http://localhost:8000/api/v1/sales \
	-H 'Content-Type: application/json' \
	-d '{"items":[{"product_id":1,"quantity":2,"unit_price":100.0}]}'
```

3. Processar fila (ou conferir jobs enfileirados) e validar inventário:

```bash
docker compose exec backend php artisan queue:work --once
```

4. Conferir métricas (se necessário):

```bash
curl http://localhost:8000/api/v1/observability/metrics
```

## Troubleshooting rápido

- Se os testes falharem localmente, verifique variáveis de ambiente em `backend/.env` e se o DB (sqlite/mysql) está configurado.
- Se usar Redis, confirme `REDIS_HOST` e `REDIS_PASSWORD` no `.env`.
- Se o endpoint `/api/v1/observability/metrics` retornar 403, configure `OBS_ALLOWED_IPS` ou deixe vazio para desenvolvimento.

---

## Contato

Autor: David Augusto

