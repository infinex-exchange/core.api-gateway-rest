CREATE ROLE "core.api-gateway-rest" LOGIN PASSWORD 'password';

create table routes(
    path varchar(255) not null,
    service varchar(255) not null
);

GRANT SELECT ON routes TO "core.api-gateway-rest";