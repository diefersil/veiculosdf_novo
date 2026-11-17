# Agent Cars

Núcleo de importação de veículos para o post type `veiculos`. O núcleo agenda as consultas, atualiza anúncios sem duplicá-los, salva taxonomias/metadados, importa fotos e associa cada anúncio ao usuário do anunciante. A lógica específica do HTML de cada loja fica em adaptadores.

## Configurar origens

No `.env` da raiz do WordPress, `AGENT_CARS_SOURCES` aceita uma lista JSON de URLs ou objetos. Uma URL simples é associada automaticamente ao adaptador que reconhece seu domínio:

```dotenv
AGENT_CARS_SOURCES='["https://casadoautomovel.com.br/seminovos-cidade-do-automovel-brasilia-df?marca=&carro-modelo=&valorInicial=&valorFinal=&anoInicial=&anoFinal="]'
AGENT_CARS_INTERVAL=hourly
AGENT_CARS_LIMIT=5
```

Uma origem pode sobrescrever o limite, escolher um adaptador e fornecer campos de perfil do anunciante:

```dotenv
AGENT_CARS_SOURCES='[{"url":"https://loja.example/estoque","adapter":"loja_example","limit":20,"seller":{"email":"vendas@loja.example","name":"Loja Example","phone":"(61) 3000-0000","whatsapp":"(61) 99999-0000","address":"Endereço","city":"Brasília","state":"DF","website":"https://loja.example","bio":"Revenda de veículos","maps_rating":""}}]'
```

Cada domínio precisa ter um adaptador registrado. Atualmente há adaptadores para `casadoautomovel.com.br`, `salaodoauto.com.br` e `brasiliamotos.com.br`. O núcleo não tenta adivinhar o HTML de uma loja desconhecida.

## Adicionar um adaptador

Crie uma classe que implemente `Agent_Cars_Adapter` (interface em `adapters/interface-agent-cars-adapter.php`). Ela precisa informar um ID estável, reconhecer URLs, extrair links da listagem, converter cada página de anúncio para o formato canônico e fornecer os dados padrão do anunciante. O resultado de `parse_vehicle()` usa `url`, `external_id`, `title`, `price`, `fields`, `images`, `description`, `brand`, `model`, `version`, `style` e `vehicle_type`.

Registre a classe pelo filtro `agent_cars_adapters`, carregando o arquivo no próprio plugin ou em um pequeno plugin de integração. Para imagens em hosts com certificados próprios, inclua os domínios em `trusted_hosts()`.

O identificador de deduplicação combina o ID do adaptador com `external_id`, então IDs iguais em lojas diferentes não colidem. Anúncios importados anteriormente também são encontrados pela URL de origem.
