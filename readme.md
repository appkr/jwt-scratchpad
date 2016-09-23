# JWT 연습 프로젝트

JWT의 작동 원리를 빠르게 이해하기 위한 프로젝트다. [JWT 관련 코드 변경 내역은 커밋 로그](https://github.com/appkr/jwt-scratchpad/commit/6276902db0b57539b2c9e1faf8c354fc1290a2e7)를 참고한다.

## 1. JWT 란?

JSON Web Token. 쿠키 메커니즘을 이용한 세션 유지를 할 수 없는/하지 않는 API 서비스에서 클라이언트를 식별하기 위한 토큰이다. 

### 1.1. 대표적인 무상태 인증 방법

1.  HTTP Basic 인증
    1. 사용자 아이디와 비밀번호를 base64로 인코딩해서 매 요청마다 Authorization 헤더에 붙여 보낸다. (e.g. `Authorization: Basic am9obkBleGFtcGxlLmNvbTpwYXNzd29yZA==`)
    2. base64 인코딩은 평문이나 마찬가지다. 즉, 네트워크 구간에서 탈취 당하면 '빵' 털린다. (e.g. `base64_decode('am9obkBleGFtcGxlLmNvbTpwYXNzd29yZA=='); //"john@example.com:password"` )
    3. 인트라넷 등 방화벽 안에서 클라이언트를 식별할 때만 유용하다.
    
2.  토큰 기반 인증
    1.  Oauth
        1. 도메인 사용자외에 별도의 `ClientId`와 `ClientSecret`가 필요하다.
        2. 페이스북 API, 유튜브 API 등 예측할 수 없는 클라이언트가 API를 소비하는 오픈 API 서비스에 적절하다.
        3. 토큰 뿐만아니라, `Client*` 발급 및 관리, 각 API 엔드포인트에 대한 접근 권한 제어를 포함하는 등, 크고 무겁다.
        4. 즉, 큰 서비스에만 어울린다.
    2.  JWT
        1. 토큰만 다룬다. 도메인 사용자를 그대로 사용하고, 도메인 사용자와 토큰간의 맵핑 테이블을 이용한다(주로 속도가 빠른 키-값 저장소 이용).
        2. 네트워크 구간에서 변조가 불가능하다. 변조되면 토큰은 무효화된다.
        3. 네트워크 구간에서 탈취 당해도 유효 기간(`ttl`) 또는 리프레시 가능 기간(`refresh_ttl`)이 지나면 무효화된다.
        4. 토큰 안에 사용자를 식별하기 위한 정보를 담고 있다. 즉, 토큰만 해독하면 사용자를 식별할 수 있다.
        5. 캡티브 API, 소형 API 서비스에 어울린다.

### 1.2. JWT 해부

이 절은 JWT 토큰 자체애 대한 설명이다.

`header.payload.signature` 형태로 구성된다. 구분자는 점(`.`)이다.

![Anatomy of JWT](http://cfile10.uf.tistory.com/image/25362B3D554DAF1E1C78EB)

1.  `header`(회색) -- 토큰 타입과 해시 알고리즘을 담고 있다(우리 프로젝트는 HMAC SHA256).

2.  `payload`(적색) -- 사용자 아이디 또는 이메일 등 사용자를 식별하기 위한 정보를 담고 있다. 역할 또는 권한을 포함할 수 있는 등, 담을 수 있는 내용에는 제한이 없다. 다만, 많은 정보를 담을수록 토큰 크기는 커지므로, 꼭 필요한 정보만 담아야 한다.

3.  `secret`(녹색) -- 해시 키를 담고 있다.

1,2,3 중 어느 한 부분이라도 변조되면 토큰은 무효화된다.

## 2. 프로젝트 설치

프로젝트를 복제한다.

```sh
~ $ git clone git@github.com:appkr/jwt-scratchpad.git

# 깃허브에 SSH 키를 등록하지 않았다면...
~ $ git clone https://github.com/appkr/jwt-scratchpad.git
```

이 프로젝트가 의존하는 라이브러리를 설치하고, 프로젝트 설정 파일을 생성한다.

```sh
# composer는 PHP의 표준 의존성 관리자다.
# 없다면 getcomposer.org를 참고한다.

$ cd jwt-scratchpad
~/jwt-scratchpad $ composer install
~/jwt-scratchpad $ cp .env.example .env
```

이 프로젝트는 SQLite를 사용한다. `database/database.sqlite`를 이미 포함하고 있으므로 DB 생성 및 마이그레이션은 필요없다. 혹시 없다면 다음 코드 블록을 참고해서 만든다.

```sh
~/jwt-scratchpad $ touch database/database.sqlite
~/jwt-scratchpad $ php artisan migrate
```

## 3. JWT 맛보기

JWT 스펙을 이해하고 구현하기는 어렵다. 해서, 이 프로젝트는 `tymon/jwt-auth` 컴포넌트를 사용하고 있다.

### 3.1. 사용자 확인

토큰을 발급 받기 위한 사용자가 `users` 테이블에 있는 지 확인한다.

```sh
~/jwt-scratchpad $ php artisan tinker
>>> App\User::first();
# => App\User {#717
#      id: "1",
#      name: "John",
#      email: "john@example.com",
#      created_at: "2016-09-22 02:59:48",
#      updated_at: "2016-09-22 02:59:48",
#    }
```

혹시 사용자가 없으면 만든다.

```sh
~/jwt-scratchpad $ php artisan tinker
>>> $u = new App\User;
>>> $u->name = 'John';
>>> $u->email = 'john@example.com';
>>> $u->password = bcrypt('password');
>>> $u->save();
```

### 3.2. 테스트 서버 구동

준비가 끝났으면 테스트를 위한 로컬 서버를 구동한다.
 
```sh
~/jwt-scratchpad $ php artisan serve --host=jwt-scratchpad.dev
# Laravel development server started on http://jwt-scratchpad.dev:8000/ 

# 라라벨 Valet 개발 서버를 이용한다면...
~/jwt-scratchpad $ valet link
# A [jwt-scratchpad] symbolic link has been created in [$HOME/.valet/Sites/jwt-scratchpad].
```

### 3.3. 테스트 클라이언트

API를 호출하기 위한 클라이언트가 필요하다. 포스트맨 크롬 익스텐션을 사용한다. [없다면 여기서 설치한다.](https://chrome.google.com/webstore/detail/postman/fhbjgbiflinjbdggehcddcbncdddomop)

[필자가 미리 만들어 놓은 API 호출을 임포트하자](https://www.getpostman.com/collections/29ea54d75668df433bef). URI의 `:host` 자리 표시자는 각자에 맞게 변경해야 한다.

![](public/images/postman1.png)

### 3.4. JWT 토큰 발급 요청

우리 시스템에는 John 사용자가 존재한다. John 사용자의 정보로 토큰을 요청해보자.

```http
# 사용할 라우트 -- https://github.com/appkr/jwt-scratchpad/blob/master/routes/api.php#L16

POST /api/tokens/create
HOST jwt-scratchpad.dev
Accept: application/json
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password"
}
```

![](public/images/postman2.png)

눈치챘겠지만 HTTP 요청 본문으로 평문으로 된 사용자 정보를 전송한다. '빵' 털릴 가능성이 큰 부분이니, 반드시 SSL(TLS)를 이용해야 한다.

현재 설정으로는(`config/jwt.php`의 `ttl`설정) 60분간 유효한 토큰이 발급된다. 60분 동안은 로그인 필요없이 토큰을 이용할 수 있으므로, 클라이언트는 자신의 로컬 저장소에 토큰을 저장해 두고 한동안 사용할 수 있다. 우리도 흉내내서 클립보드에 저장해두자(복사한다).

### 3.5. 리소스 요청

3.3 절에서 발급받은 토큰으로 보호된 라우트를 요청해 보자. API 콜한다는 얘기다. 3.3 절에서 발급 받은 JWT 토큰은 `Authorization` HTTP 요청 헤더로 제출해야 한다.

```http
# 사용할 라우트 -- https://github.com/appkr/jwt-scratchpad/blob/master/routes/api.php#L27

GET /api/users
HOST jwt-scratchpad.dev
Accept: application/json
Authorization: Bearer eyJ***.eyJ***.ZGS***
```

![](public/images/postman3.png)

토큰이 정상 작동하는 것을 확인했다. 토큰을 변조해서 요청했을 때 어떤 메시지가 반환되는지도 확인해 보자. 다시 말하면 레인보우 테이블 공격 등에 안전하다는 의미다.

우리의 토큰은 사용자의 아이디를 담고 있다. 처음 발급 받을 때, 우리의 JWT 컴포넌트는 라라벨의 캐시 저장소에 토큰과 사용자 아이디의 맵핑 테이블을 만들어 두었다. 말인즉슨, 토큰이 온전히 서버에 전달되어 해독된다면 사용자를 식별하기 위한 SQLite 쿼리는 필요없다는 얘기다. 세 번만 빨리 말해보자. '빠르다'. SQLite 쿼리를 감사하기 위한 코드는 [여기](https://github.com/appkr/jwt-scratchpad/blob/master/routes/api.php#L31)에 있으니, SQLite 쿼리가 발생하는지 확인해 보라. 쿼리 로그는 `storage/logs/laravel.log`에 저장된다.

### 3.6. 토큰 리프레시 요청

현재 설정은 토큰 유효 기간 60분, 리프레시 가능 기간 2주다. 60분이 지나면 기존 토큰으로 새 토큰을 교환 받을 수 있다. 그런데 주의할 점은 새 토큰을 장착했다고해서 리프레시 가능 기간이 다시 2주가 아니란 점이다. 2주는 최초 발급 시점으로 부터 2주란 뜻이며, 최초 토큰 발급 후 2주가 지나면 무조건 로그인 창을 띄우고 완전 새삥 토큰을 발급 받아야 한다.

토큰이 탈취 당하면, 60분 동안 John 사용자를 빙의하며 John 사용자가 접근할 수 있는 서버의 리소스를 휘젓고 다닐 수 있다. 또 리프레시 방법을 알고 있다면 2주 동안 토큰을 갱신해 가며 계속 나쁜 짓을 할 수 있다. 그러나, 2주가 지나면 로그인 창이 뜨고, 나쁜 짓은 종지부를 찍어야 한다. 

```php
// config/jwt.php

return [
    'ttl' => 60, // 분
    'refresh_ttl' => 20160, // 분
];

```

```http
# 사용할 라우트 -- https://github.com/appkr/jwt-scratchpad/blob/master/routes/api.php#L21

GET /api/tokens/refresh
HOST jwt-scratchpad.dev
Accept: application/json
Authorization: Bearer eyJ***.eyJ***.ZGS***
```

![](public/images/postman4.png)

클라이언트는 갱신된 토큰을 받으면 기존과 똑같이 로컬 저장소에 저장하고 60분 동안 API 콜을 할 수 있다. 참고로 토큰을 갱신하면 기존 토큰은 폐기된다.

## 4. 오류 메시지 및 클라이언트 구현 모범 사례

다음은 이 프로젝트의 오류 메시지와 그에 따른 클라이언트의 동작이다.

응답 코드|오류 메시지|클라이언트의 구현 사례
---|---|---
`400`|`token_not_provided`|- 인증이 필요한 라우트다.
&nbsp;|&nbsp;|- 저장된 토큰이 없다면 사용자 로그인 UI를 표시하고 서버에 토큰을 요청한다.  
`401`|`token_expired`|- 토큰의 유효 기간이 만료됐다.
&nbsp;|&nbsp;|- 토큰 교체를 요청하고 새로 받은 토큰으로 리소스 요청을 다시 한다. 
`401`|`refresh_ttl_finished`|- 토큰 교체 가능 기간이 지났다.
&nbsp;|&nbsp;|- 사용자 로그인 UI를 표시하고, 새 토큰을 요청한다.
`400`|`token_invalid`|- 토큰이 변조되었다.
&nbsp;|&nbsp;|- 사용자 로그인 UI를 표시하고, 새 토큰을 요청한다.
`404`|`user_not_found`|- 토큰 본문에 포함된 사용자가 현재는 서비스에 없다(탈퇴 등).