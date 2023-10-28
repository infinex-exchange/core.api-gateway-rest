insert into routes(path, service) values
    ('/', 'temp.legacy-api'),
    ('/account/v2', 'account.account'),
    ('/account/v2/mfa', 'account.mfa'),
    ('/affiliate/v2', 'affiliate.affiliate'),
    ('/wallet/v2', 'wallet.wallet'),
    ('/wallet/v2/io', 'wallet.io'),
    ('/wallet/v2/addressbook', 'wallet.addressbook'),
    ('/info/v2/popup', 'info.popup'),
    ('/info/v2/announcements', 'info.announcements'),
    ('/info/v2/support', 'info.support'),
    ('/vote/v2', 'vote.vote').
    ('/mining/v2', 'mining.mining');