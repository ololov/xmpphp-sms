XMPPHP-SMS is the plugin for [XMPPHP](http://code.google.com/p/xmpphp).

XMPPHP-SMS allows you to send SMS via [mrim](http://svn.xmpp.ru/repos/mrim/) ([Mail.Ru Agent](http://agent.mail.ru/) jabber transport).

With XMPPHP-SMS you can:
  * connect to XMPP server (thanks to XMPPHP)
  * wait until mrim transport become online (with configurable timeout)
  * send sms with optional auto transliteration (with configurable timeout)
  * wait for possible sms send errors (errors usually sent by Mail.Ru Agent in a minute interval after sms send)

With XMPPHP-SMS you can NOT:
  * register on mrim transport
  * enable or disable mrim transport

Please note that there are message send limits by Mail.Ru Agent!

For installation and usage help see our wiki: [Installation](Installation.md), [Usage](Usage.md).