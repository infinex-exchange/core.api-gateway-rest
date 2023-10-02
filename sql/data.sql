insert into routes(path, service) values

    ('/account', 'temp.legacy-api'),
    ('/wallet', 'temp.legacy-api'),
    ('/spot', 'temp.legacy-api'),
    ('/info', 'temp.legacy-api'),
    ('/bridge', 'temp.legacy-api'),
    ('/mining', 'temp.legacy-api'),
    ('/p2p', 'temp.legacy-api'),
    ('/nft', 'temp.legacy-api'),
    ('/gamble', 'temp.legacy-api'),
    ('/ipc', 'temp.legacy-api'),
    
    ('/account/v2', 'account.accountd'),
    ('/affiliate/v2', 'affiliate.affiliate'),
    ('/wallet/v2', 'wallet.wallet');