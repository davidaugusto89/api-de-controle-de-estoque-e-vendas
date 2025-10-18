# API de Controle de Estoque e Vendas

Este repositório contém uma API para controle de estoque e vendas desenvolvida em Laravel.

## Objetivo

Fornecer uma API RESTful para gerenciar produtos, inventário e vendas, com recursos para relatórios e integração.

## Requisitos

- PHP 8.1+ (compatível com a versão usada no projeto)
- Composer
- Docker & Docker Compose (recomendado)
- MySQL (ou via Docker)

## Como rodar (com Docker)

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

## Como rodar localmente (sem Docker)

1. Instale dependências:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

2. Configure o banco no `.env` e rode migrations:

```bash
php artisan migrate --seed
php artisan serve
```

## Testes

Executar testes PHPUnit:

```bash
cd backend
./vendor/bin/phpunit
```

## Estrutura principal

- `app/` - Código da aplicação (Domain, Application, Infrastructure)
- `routes/` - Rotas da aplicação
- `database/` - Migrations, factories e seeders
- `tests/` - Testes automatizados

## Contribuição

Regras básicas:

- Abra issues para bugs e features
- Use branches por feature/bugfix
- Prefira PRs pequenas e com descrição clara

## Contato

Autor: David

---

Arquivo gerado automaticamente pelo assistente para iniciar o projeto.

