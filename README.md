# 🧾 API de Controle de Estoque e Vendas

Aplicação backend desenvolvida em **Laravel** para gerenciamento de **produtos**, **estoque** e **vendas**, com foco em **performance**, **concorrência**, **processamento assíncrono** e **observabilidade**.

---

## 📚 Sumário

- [Visão Geral](#-visão-geral)
- [Arquitetura e Tecnologias](#-arquitetura-e-tecnologias)
- [Requisitos](#-requisitos)
- [Como Executar (Docker)](#-como-executar-docker)
- [Execução Local (sem Docker)](#-execução-local-sem-docker)
- [Testes e Cobertura](#-testes-e-cobertura)
 - [Cobertura de Código (atual)](#-cobertura-de-código-atual)
- [Filas e Agendador](#-filas-e-agendador)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Serviços adicionais e como acessar](#-serviços-adicionais-e-como-acessar)
- [Estrutura do Projeto (detalhada)](#-estrutura-do-projeto-detalhada)
- [Observabilidade e Métricas](#-observabilidade-e-métricas)
- [Endpoints Principais](#-endpoints-principais)
- [Otimizações e Estratégias](#-otimizações-e-estratégias)
- [Validação Local](#-validação-local)
- [Melhorias Futuras](#-melhorias-futuras)
- [Autor](#-autor)

---

## 🎯 Visão Geral

Esta API foi projetada para demonstrar boas práticas de desenvolvimento backend em **Laravel 12**, implementando recursos modernos como **processamento em filas**, **cache inteligente**, **transações idempotentes** e **mecanismos de concorrência** para garantir integridade dos dados em operações críticas.

O sistema simula o módulo de controle de estoque e vendas de um ERP, oferecendo endpoints para:
- Registro e consulta de produtos e inventário
- Processamento de vendas com múltiplos itens
- Relatórios filtráveis por data e SKU
- Métricas e observabilidade simplificadas

---

## 🧩 Arquitetura e Tecnologias

- **Framework:** Laravel 12
- **Linguagem:** PHP 8.4
- **Banco de Dados:** MySQL 8.4
- **Filas e Cache:** Redis
- **Testes:** PHPUnit
- **Scheduler:** Cron Jobs via Laravel Scheduler
- **Observabilidade:** Métricas via endpoint Prometheus-style

---

## 🧱 Arquitetura Hexagonal (Ports & Adapters)

A aplicação segue **Arquitetura Hexagonal** para isolar o **Domínio** das preocupações de infraestrutura, promovendo testabilidade e facilidade de evolução. Em alto nível:

```
[Drivers/Entradas] → Application (Use Cases) → Domain (Entities/Services) → [Ports] → Adapters (Infra)
```

### Camadas e mapeamento de pastas

- **Domain** (`app/Domain`)
  - **Entities**: modelos ricos de domínio (ex.: `InventoryItem`, `SaleAggregate`).
  - **Services**: regras de negócio puras (`StockPolicy`, `MarginCalculator`, `SaleValidator`).
  - **ValueObjects**: tipos imutáveis (`Money`, `DateRange`).
  - **Enums/Exceptions**: estados e falhas de domínio (`SaleStatus`, `InventoryInsufficientException`).

- **Shared/Contracts (Ports)** (`app/Domain/Shared/Contracts`)
  - **RepositoryInterface**, **CacheInterface**: definem contratos que a camada de aplicação usa sem conhecer a implementação.

- **Application (Use Cases)** (`app/Application/**/UseCases`)
  - Orquestram fluxos, transações e integração com portas: `CreateSale`, `FinalizeSale`, `GetSaleDetails`, `GetInventorySnapshot`, `RegisterStockEntry`, `CleanupOldInventory`, `GenerateSalesReport`.
  - Coordenam **eventos** e **jobs** sem conter regra de negócio detalhada.

- **Adapters/Infra** (`app/Infrastructure`)
  - **Persistence/Eloquent**: repositórios concretos (`ProductRepository`, `InventoryRepository`, `SaleRepository`, `SaleItemRepository`).
  - **Queries (read models)**: consultas otimizadas para endpoints (`InventoryQuery`, `SaleDetailsQuery`, `SalesReportQuery`).
  - **Cache**: `InventoryCache` implementa caching com versionamento.
  - **Locks**: `RedisLock` para controle de concorrência.
  - **Events/Listeners/Jobs**: integração assíncrona (`SaleFinalized`, `UpdateInventoryListener`, `FinalizeSaleJob`, `UpdateInventoryJob`).
  - **Metrics**: `MetricsCollector` para observabilidade mínima.

- **Drivers (Primary Adapters)**
  - **HTTP** (`app/Http`): controllers, requests e resources (`InventoryController`, `SaleController`, etc.)
  - **Providers** (`app/Providers`): IoC/DI e rate limiting.

### Fluxo típico (exemplo de venda)

1. **HTTP** chama `POST /api/sales` → `SaleController` valida `CreateSaleRequest`.
2. **Application** executa `CreateSale` (orquestração) e publica evento `SaleFinalized`.
3. **Listener** aciona `UpdateInventoryJob` em **fila**.
4. **Job** usa **portas** (repositórios, cache, locks) para atualizar estoque:
   - Decremento atômico no banco com **WHERE quantity >= ?**.
   - **RedisLock** por SKU opcional para serializar contenda.
   - **Transação + idempotência** para reprocessamentos seguros.
5. **Cache** de inventário é invalidado por **versionamento**.

### Benefícios práticos

- **Testabilidade**: Domínio e casos de uso testados sem subir framework/banco.
- **Evolução segura**: troca de adapters (p.ex., Eloquent → outro ORM) sem afetar o domínio.
- **Performance e resiliência**: separação explícita de **read models** (`Queries`) e **write models** (regras de domínio), com filas e locks para cenários concorrentes.
- **Claridade arquitetural**: cada mudança tem lugar definido (regra de negócio no domínio, orquestração na aplicação, integração na infraestrutura).

---

## ⚙️ Requisitos

- PHP 8.1+
- Composer
- Docker & Docker Compose (recomendado)
- Banco de dados configurado (MySQL/PostgreSQL/SQLite)

---

## 🚀 Como Executar (Docker)

```bash
cp backend/.env.example backend/.env
docker-compose up -d --build
docker compose exec backend php artisan migrate --seed
```

A API ficará disponível conforme definido no arquivo `docker-compose.yml` (porta padrão: `8000`).

---

## 💻 Execução Local (sem Docker)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 🧪 Testes e Cobertura

Rodar todos os testes unitários e de integração:

```bash
cd backend
./vendor/bin/phpunit
```

Testes principais de integração e concorrência:

```bash
./vendor/bin/phpunit tests/Feature/Integration/SaleFlowIntegrationTest.php \
  tests/Feature/Integration/ConcurrentSalesTest.php \
  tests/Feature/Integration/IdempotentRetryJobTest.php
```

---

## 🕓 Filas e Agendador

### Local (sem Docker)

```bash
php artisan queue:work --tries=3 --sleep=3 --queue=default,inventory,sales
php artisan schedule:run
```

### Com Docker

```bash
docker compose exec backend php artisan queue:work --tries=3 --sleep=3 --queue=default,inventory,sales
docker compose exec backend php artisan schedule:run
```

> O projeto utiliza **Redis** como driver de fila/cache. Horizon pode ser configurado para monitoramento em produção.

---

## 🧱 Estrutura do Projeto

```
app/           -> Código principal (Domain, Application, Infrastructure)
routes/        -> Definição de rotas
config/        -> Configurações da aplicação
database/      -> Migrations, factories e seeders
tests/         -> Testes automatizados
```

## 🛠️ Serviços adicionais e como acessar

O projeto traz configurações e/ou exemplos para executar serviços que tipicamente acompanham uma aplicação Laravel em produção e em ambiente de desenvolvimento com Docker. Abaixo estão os serviços com instruções rápidas de acesso, portas padrão (quando aplicável) e variáveis de ambiente relevantes.

- MySQL
  - Uso: banco de dados principal da aplicação.
  - Porta padrão (host): 3306 (pode ser mapeada no `docker-compose.yml`).
  - Variáveis importantes: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (arquivo `backend/.env`).
  - Acessar via CLI do container:

    ```bash
    docker compose exec backend mysql -h$DB_HOST -P$DB_PORT -u$DB_USERNAME -p$DB_PASSWORD $DB_DATABASE
    ```

- Redis
  - Uso: driver de cache, sessão e filas (queues/Horizon).
  - Porta padrão (host): 6379.
  - Variáveis: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`.
  - Exemplo para checar chaves:

    ```bash
    docker compose exec redis redis-cli -h 127.0.0.1 -p 6379 ping
    ```

- Nginx (proxy reverso)
  - Uso: servir `public/` e rotear para o container PHP-FPM em produção/local via Docker.
  - Configuração: `deploy/nginx/default.conf` contém um exemplo de configuração.
  - Porta padrão (host): 80/443 (ajustável no `docker-compose.yml`).

- PHP-FPM / Backend container
  - Uso: executa o Laravel (arquivo `backend/Dockerfile`, `deploy/php/fpm.conf`).
  - Entrypoint: `backend/entrypoint.sh`.
  - Para executar comandos artisan:

    ```bash
    docker compose exec backend php artisan migrate --seed
    docker compose exec backend php artisan queue:work --once
    ```

- Horizon (opcional)
  - Uso: painel e processo de filas para Redis (se estiver habilitado).
  - Como rodar: dentro do container `backend` execute `php artisan horizon` ou configure no `docker-compose`.
  - URL de monitoramento (se exposto): normalmente algo como `http://localhost:8080/horizon` dependendo do mapeamento.

- Observability stack (Prometheus, Grafana, Alertmanager)
  - Prometheus
    - Uso: coletor/raspador de métricas (ex.: endpoint `/api/v1/observability/metrics`).
    - Arquivo de exemplo na pasta `monitoring/prometheus/`.
    - Porta padrão: 9090.
  - Grafana
    - Uso: dashboard visual (ex.: importar `monitoring/grafana/dashboards/` quando disponível).
    - Porta padrão: 3000.
  - Alertmanager
    - Uso: gerenciar alertas enviados pelo Prometheus.
    - Porta padrão: 9093.

- Acesso aos serviços via Docker Compose
  - Subir tudo:

    ```bash
    docker-compose up -d --build
    ```

  - Verificar logs:

    ```bash
    docker compose logs -f backend
    docker compose logs -f redis
    docker compose logs -f mysql
    ```

## 🔍 Estrutura do Projeto (detalhada)

Uma visão expandida das pastas principais e seus propósitos para facilitar navegação e contribuição:

```
backend/                       -> Container/backend Laravel
  ├─ app/                       -> Código principal da aplicação
  │   ├─ Application/           -> Casos de uso / orquestração (Use Cases)
  │   │   ├─ Inventory/         -> Use cases relacionados a inventário
  │   │   ├─ Reports/           -> Use cases para geração de relatórios
  │   │   └─ Sales/             -> Use cases relacionados a vendas
  │   ├─ Domain/                -> Entidades, Value Objects e regras de negócio
  │   │   ├─ Inventory/
+  │   │   ├─ Sales/
  │   │   └─ Shared/
  │   ├─ Exceptions/            -> Formatação e tratamento de exceções
  │   ├─ Http/                  -> Controllers, Requests, Resources, Middleware
  │   │   ├─ Controllers/
  │   │   ├─ Middleware/
  │   │   ├─ Requests/
  │   │   └─ Resources/
  │   ├─ Infrastructure/        -> Adapters para infra (cache, persistence, locks, jobs)
  │   │   ├─ Cache/
  │   │   ├─ Events/
  │   │   ├─ Jobs/
  │   │   ├─ Listeners/
  │   │   ├─ Locks/
  │   │   ├─ Metrics/
  │   │   └─ Persistence/
  │   ├─ Models/                -> Eloquent models (Product, Inventory, Sale, SaleItem, User)
  │   └─ Providers/             -> Service providers e bindings de IoC
  ├─ bootstrap/                 -> bootstrap do framework e cache de providers
  ├─ config/                    -> Arquivos de configuração (database, queue, cache, observability)
  ├─ database/                  -> Migrations, factories e seeders
  │   ├─ factories/
  │   ├─ migrations/
  │   └─ seeders/
  ├─ public/                    -> Ponto de entrada web (index.php)
  ├─ resources/                 -> Views, assets e lang (se aplicável)
  ├─ routes/                    -> Arquivos de rotas (`api.php`, `web.php`, `console.php`)
  ├─ storage/                   -> Logs, cache, uploads temporários
  └─ tests/                     -> Testes Unitários e de Feature
      ├─ Feature/
      └─ Unit/

deploy/                        -> Configurações para deploy (Nginx, PHP-FPM, opcache)
  ├─ nginx/
  │   └─ default.conf
  └─ php/
      ├─ fpm.conf
      └─ opcache.ini

monitoring/                    -> Configs e dashboards para Prometheus/Grafana/Alertmanager
  ├─ alertmanager/
  ├─ grafana/
  └─ prometheus/

docs/                          -> Documentação adicional e notas arquiteturais
coverage/                      -> Resultados de cobertura gerados pelo PHPUnit

README.md                      -> Este arquivo
docker-compose.yml             -> Definições dos serviços para desenvolvimento e integração
"""

---

## 📊 Observabilidade e Métricas

Endpoint de métricas estilo Prometheus:
```bash
GET /api/v1/observability/metrics
```

- Protegido por IP (`config/observability.php > allowed_ips`)
- Pode ser configurado via variável `OBS_ALLOWED_IPS`
- Ideal para scraping por Prometheus local ou remoto

---

## 🔗 Endpoints Principais

| Método | Endpoint | Descrição |
|--------|-----------|------------|
| `POST` | `/api/inventory` | Registrar entrada de produtos no estoque |
| `GET` | `/api/inventory` | Consultar situação atual do estoque (cacheada) |
| `POST` | `/api/sales` | Registrar nova venda (processamento assíncrono) |
| `GET` | `/api/sales/{id}` | Detalhar uma venda específica |
| `GET` | `/api/reports/sales` | Gerar relatório de vendas com filtros |

---

## ⚡ Otimizações e Estratégias

- **Decremento atômico:** evita race conditions em atualizações concorrentes de estoque (`UPDATE ... WHERE quantity >= ?`).
- **Locks por produto:** via `InventoryLockService` (Redis lock) para serializar atualizações.
- **Transações e idempotência:** todas as operações críticas executadas com rollback seguro.
- **Cache versionado:** invalidação de listas via chave `inventory:list_version`.
- **Processamento assíncrono:** filas para vendas e atualização de estoque.
- **Observabilidade mínima:** métricas de jobs, cache e inventário expostas via endpoint.
- **Testes de concorrência:** garantem integridade e rollback correto sob carga.

Essas práticas asseguram **consistência**, **baixa latência** e **facilidade operacional**.

---

## 🧭 Validação Local

1. Migrar e popular banco:
   ```bash
   docker compose exec backend php artisan migrate --seed
   ```

2. Criar uma venda:
   ```bash
   curl -X POST http://localhost:8000/api/v1/sales \
   -H 'Content-Type: application/json' \
   -d '{"items":[{"product_id":1,"quantity":2,"unit_price":100.0}]}'
   ```

3. Processar fila e validar estoque:
   ```bash
   docker compose exec backend php artisan queue:work --once
   ```

4. Consultar métricas:
   ```bash
   curl http://localhost:8000/api/v1/observability/metrics
   ```

---

## ✅ Cobertura de Código (atual)

No último relatório de cobertura gerado (veja `coverage/`), a cobertura total está em aproximadamente **80.7%**. Um screenshot anexo mostra que pastas como `Http` e `Infrastructure` têm cobertura mais baixa e são bons alvos para priorização.

![Coverage screenshot](https://raw.githubusercontent.com/davidaugusto89/api-de-controle-de-estoque-e-vendas/refs/heads/main/docs/coverage-screenshot.png)


> ⚠️ **Atenção**
> **Recomendação:** aumentar a cobertura para **90%+** como meta de médio prazo,
> priorizando **testes de integração** e **casos de borda** nas camadas com menor cobertura.



## 🚧 Melhorias Futuras

- Integração com **Prometheus** ou **Grafana** para métricas avançadas.
- Implementação de **CI/CD** (GitHub Actions) com PHPUnit e Pint.
- Configuração de **Sentry** para monitoramento de exceções.
- Exposição de **API Docs (Swagger)** automatizada.
- Adição de **autenticação JWT** e controle de permissões.
- Aumentar cobertura de testes para **90%+**; priorizar pastas com baixa cobertura (`Http`, `Infrastructure`) e criar testes de integração para fluxos críticos (vendas, atualização de estoque, jobs idempotentes).

---

## 👨‍💻 Autor

**David Augusto**

