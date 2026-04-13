export type RouteDataScope = {
  kind: 'global' | 'mixed';
  tone: 'info' | 'warning';
  title: string;
  description: string;
};

const routeScopeMap: Array<[path: string, scope: RouteDataScope]> = [
  [
    '/audit-trail',
    {
      kind: 'mixed',
      tone: 'warning',
      title: 'Audit trail \u01b0u ti\u00ean shell branch nh\u01b0ng v\u1eabn c\u00f2n ngo\u1ea1i l\u1ec7',
      description: 'Shell branch hi\u1ec7n \u0111\u01b0\u1ee3c g\u1eedi th\u00e0nh branch_id cho audit trail. C\u00e1c event h\u1ec7 th\u1ed1ng kh\u00f4ng g\u1eafn branch ho\u1eb7c kh\u00f4ng g\u1eafn subject theo branch c\u00f3 th\u1ec3 c\u1ea7n chuy\u1ec3n sang to\u00e0n ph\u1ea1m vi \u0111\u1ec3 \u0111i\u1ec1u tra.',
    },
  ],
];

export function resolveRouteDataScope(pathname: string): RouteDataScope | null {
  const matched = routeScopeMap.find(([path]) => pathname === path || pathname.startsWith(`${path}/`));
  return matched?.[1] ?? null;
}
