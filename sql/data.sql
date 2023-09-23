CREATE ROLE core.api-gateway-rest LOGIN PASSWORD 'password';

create table routes(
    path varchar(255) not null,
    service varchar(255) not null
);

GRANT SELECT ON routes TO core.api-gateway-rest;

insert into routes(path, service) values
    ('/account', 'temp.api-legacy'),
    ('/wallet', 'temp.api-legacy'),
    ('/spot', 'temp.api-legacy'),
    ('/info', 'temp.api-legacy'),
    ('/bridge', 'temp.api-legacy'),
    ('/mining', 'temp.api-legacy'),
    ('/p2p', 'temp.api-legacy'),
    ('/nft', 'temp.api-legacy'),
    ('/gamble', 'temp.api-legacy'),
    ('/ipc', 'temp.api-legacy');