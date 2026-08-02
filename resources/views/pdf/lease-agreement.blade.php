<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato de arrendamiento #{{ $contract->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.45; text-align: justify; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 14px; text-transform: uppercase; }
        h2 { font-size: 12px; margin: 14px 0 6px; text-transform: uppercase; }
        p { margin: 0 0 8px; }
        ul { margin: 0 0 8px 18px; padding: 0; }
        li { margin: 0 0 4px; }
        .signature-table { width: 100%; margin-top: 36px; border-collapse: collapse; }
        .signature-table td { width: 50%; text-align: center; vertical-align: top; padding-top: 40px; }
        .signature-line { border-top: 1px solid #0f172a; display: inline-block; width: 80%; padding-top: 4px; }
        .uppercase { text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>Contrato de arrendamiento</h1>

    <p>
        Ensenada, Baja California, a {{ $document_day }} de {{ $document_month }} de {{ $document_year }},
        celebran el contrato de arrendamiento, por una parte
        <strong>{{ $landlord_name }}</strong>@if (! empty($landlord_rep)), representada por <strong>{{ $landlord_rep }}</strong>@endif,
        a quien en lo sucesivo se le denominará <span class="uppercase">«ARRENDADOR»</span>, y por la otra parte
        <strong>{{ $tenant_name }}</strong>, a quien en lo sucesivo se le denominará <span class="uppercase">«ARRENDATARIO»</span>,
        al tenor de las siguientes declaraciones y cláusulas.
    </p>

    <h2>Declaraciones</h2>
    <p>
        <span class="uppercase">«EL ARRENDATARIO»</span> declara ser persona física, presentando su credencial federal para votar
        CLAVE <strong>{{ $tenant_ine !== '' ? $tenant_ine : '________________________' }}</strong> como identificación oficial,
        quedando así acreditada su personalidad a satisfacción del <span class="uppercase">«ARRENDADOR»</span>.
    </p>
    <p>
        <span class="uppercase">«EL ARRENDADOR»</span> expresa su deseo de dar en arrendamiento el inmueble de su propiedad descrito en la declaración anterior.
    </p>
    <p>
        <span class="uppercase">«EL ARRENDATARIO»</span> expresa que conoce el inmueble objeto del presente contrato, mismo que se encuentra en perfectas condiciones
        y acepta celebrar el presente contrato.
    </p>

    <h2>Cláusulas</h2>

    <p>
        <strong>PRIMERA.</strong> <span class="uppercase">«ARRENDADOR»</span> otorga en arrendamiento la totalidad del inmueble ubicado en
        <strong>{{ $property_address }}</strong>.
    </p>

    <p>
        <strong>SEGUNDA.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> acepta el inmueble otorgado en arrendamiento, en las condiciones de conservación
        y funcionalidad, conociendo debidamente el bien inmueble objeto del presente contrato. Así se obliga al
        <span class="uppercase">«ARRENDATARIO»</span> a devolver el inmueble al <span class="uppercase">«ARRENDADOR»</span> en las mismas condiciones en las que lo recibió,
        sin más deterioro que el causado del uso normal; de lo contrario se pagará el precio de la reparación o reposición necesaria.
    </p>

    <p>
        <strong>TERCERA. VIGENCIA DEL CONTRATO.</strong> La vigencia del presente contrato será de <strong>{{ $term_description }}</strong>
        contados a partir del día <strong>{{ $starts_at }}</strong> al día <strong>{{ $ends_at }}</strong>, obligatorio el cumplimiento del plazo para ambas partes.
    </p>

    <p>
        <strong>CUARTA.</strong> Citando la Ley de Arrendamiento, N. 30201, ley de desalojo por inclusión de la cláusula de allanamiento al futuro del arrendatario,
        posibilita la rescisión inmediata de los predios arrendados. Esta ley dicta que aquellos inquilinos que, en caso de vencimiento de contrato o rescisión por
        falta de pago de mensualidad por dos meses consecutivos o adeuden por tres meses seguidos conceptos como cuota de mantenimiento, servicios de agua, luz u otro
        servicio involucrado, renuncian a su derecho de contestar, agilizando el trámite del proceso de desalojo del inmueble que arriendan.
    </p>
    <ul>
        <li>
            El <span class="uppercase">«ARRENDATARIO»</span> se compromete a abandonar el predio por vencimiento del contrato o por incumplimiento de dos meses de renta.
        </li>
        <li>
            El desalojo se notificará al <span class="uppercase">«ARRENDATARIO»</span> y tendrá 6 días para contestar acreditando la vigencia del contrato o cancelando su deuda.
            En caso de no obtener respuesta, se emitirá una orden de desalojo a ejecutarse en 15 días hábiles.
        </li>
        <li>
            La renta impaga judicialmente reconocida origina su inscripción en el registro de Deudores Judiciales Morosos y tendrá vigencia hasta la extinción de la obligación.
        </li>
    </ul>

    <p>
        <strong>QUINTA. USO DEL INMUEBLE.</strong> Las partes convienen en que el inmueble dado en arrendamiento se usará para <strong>CASA HABITACIÓN</strong>;
        uso diferente o fuera del orden legal será causal de rescisión en contra del <span class="uppercase">«ARRENDATARIO»</span>.
    </p>

    <p>
        <strong>SEXTA.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> pagará por concepto de renta la cantidad de
        <strong>${{ $rent_amount_formatted }}</strong> ({{ $rent_amount_words }}) el día
        <strong>{{ $due_day }}</strong> de cada mes, en el domicilio a arrendar
        <strong>{{ $property_address }}</strong>.
    </p>
    <p>
        <strong>6.1.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> deberá pagar puntualmente la cantidad pactada (no mayor de los 4 primeros días);
        en su defecto la renta fijada sufrirá un incremento automático de $100.00 (cien pesos moneda nacional) por día y el 10% (diez por ciento) del importe semanal
        retrasado, junto con el importe de la renta.
    </p>

    <p>
        <strong>SÉPTIMA. CONSERVACIÓN DEL INMUEBLE.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> toma posesión del inmueble y la responsabilidad de los gastos
        necesarios para el mantenimiento del inmueble, así como los servicios contratados o que se contraten para el buen funcionamiento del bien
        dado en arrendamiento, conservándolo en todo momento en estado satisfactorio para su uso u ocupación.
        <span class="uppercase">«EL ARRENDADOR»</span> autoriza al <span class="uppercase">«ARRENDATARIO»</span> para hacer reparaciones urgentes e indispensables;
        asimismo <span class="uppercase">«EL ARRENDATARIO»</span> renuncia a cualquier acción para solicitar su reembolso.
    </p>
    <p>
        <strong>7.1.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> renuncia a recibir retribución o reembolso alguno por los gastos que efectúe por concepto de
        servicios, mantenimiento y conservación del inmueble objeto del presente contrato, mismo al término, tomando en cuenta la higiene y limpieza.
    </p>
    <p>
        <strong>7.2.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> conoce perfectamente las condiciones de higiene a las que obliga la ley y se obliga a
        conservarlas a su costa:
    </p>
    <ul>
        <li>Limpieza e higiene del inmueble alquilado (cocina y equipo de cocina, recámaras, baños, regaderas y cuarto de lavado, así como su equipo).</li>
        <li>Espacio de entrada, así como área común.</li>
        <li>En caso de contar con espacio de estacionamiento, mantenerlo limpio.</li>
        <li>Área de basura: colocar la basura dentro del contenedor.</li>
    </ul>

    <p>
        <strong>OCTAVA. CASO FORTUITO O DE FUERZA MAYOR.</strong> Ninguna de las dos partes será responsable en caso de destrucción o deterioro del inmueble que impida su uso
        u ocupación, siempre y cuando estos sean de fuerza mayor y sea imposible su prevención y predicción; sin embargo
        <span class="uppercase">«EL ARRENDATARIO»</span> será responsable civilmente de pagar la indemnización respectiva a favor del
        <span class="uppercase">«ARRENDADOR»</span> cuando dichos daños o pérdida del inmueble sean consecuencia de negligencia imputable a su persona.
        <span class="uppercase">«EL ARRENDATARIO»</span> es responsable de entregar el inmueble en las mismas condiciones en que fue entregado en un término no mayor a
        60 días naturales contados a partir de ocurrida la pérdida o daño.
    </p>
    <p>
        <strong>8.1.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> se hace responsable y toma conciencia sobre la sanción por pérdida de llave del monto de
        $50.00 (cincuenta pesos, moneda nacional).
    </p>

    <p>
        <strong>NOVENA. MODIFICACIONES DEL CONTRATO.</strong> Las partes podrán acordar el incremento de la vigencia establecida originalmente, debiendo formalizarse por
        escrito mediante convenio modificatorio.
    </p>

    <p>
        <strong>DÉCIMA.</strong> Queda prohibido realizar celebraciones, fiestas o reuniones en el área común o dentro de la casa habitación arrendada, debiendo respetar
        las siguientes reglas:
    </p>
    <ul>
        <li>Por respeto a inquilinos vecinos, el ruido está prohibido después de las 22:00 hrs.</li>
        <li>
            Por respeto a inquilinos vecinos y a la limpieza e higiene común, todo rastro de basura debe ser colocado en el contenedor de basura,
            así como mantener el área común despejada de rastros de basura.
        </li>
    </ul>

    <p>
        <strong>DÉCIMA PRIMERA. DAÑOS Y PERJUICIOS.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> se obliga a responder al
        <span class="uppercase">«ARRENDADOR»</span> en los casos de negligencia y falta de pericia en la seguridad del bien objeto del presente contrato siempre y cuando sean
        imputables a su persona. <span class="uppercase">«EL ARRENDATARIO»</span> libera de toda responsabilidad por daños a terceros del
        <span class="uppercase">«ARRENDADOR»</span> que se puedan ocasionar por el uso, goce y disfrute del inmueble.
    </p>
    <p>
        <strong>11.1. USO ILÍCITO DEL INMUEBLE Y PROHIBICIÓN DE SUSTANCIAS ILÍCITAS.</strong>
        <span class="uppercase">«EL ARRENDATARIO»</span> se obliga a hacer uso del inmueble única y exclusivamente para el uso habitacional convenido,
        quedando estrictamente prohibido utilizarlo, permitir, facilitar o tolerar que terceros lo utilicen para la realización de cualquier actividad ilícita.
        En particular, queda prohibido dentro del inmueble:
    </p>
    <ul>
        <li>
            a) Poseer, almacenar, producir, fabricar, distribuir, comercializar o suministrar drogas o sustancias cuya posesión, distribución o comercialización
            sea ilícita conforme a la legislación aplicable.
        </li>
        <li>
            b) Almacenar, introducir o utilizar armas, objetos o materiales cuya posesión sea ilícita o que sean utilizados para la comisión de algún delito.
        </li>
        <li>
            c) Realizar, permitir o facilitar actividades relacionadas con delincuencia organizada, trata de personas, prostitución, explotación sexual, fraude,
            extorsión o cualquier otra actividad delictiva.
        </li>
        <li>
            d) Utilizar el inmueble como punto de distribución, almacenamiento, reunión o coordinación para actividades ilícitas.
        </li>
        <li>
            e) Permitir que terceras personas utilicen el inmueble para cualquiera de los fines anteriormente señalados, aun cuando
            <span class="uppercase">«EL ARRENDATARIO»</span> no participe directamente en dichas actividades.
        </li>
    </ul>
    <p>
        <strong>11.2.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> será responsable del uso que se dé al inmueble durante la vigencia del contrato,
        así como de las conductas realizadas por terceras personas (familiares, ocupantes, visitantes, invitados, etc.) a quienes permita el acceso al inmueble.
        <span class="uppercase">«EL ARRENDATARIO»</span> reconoce expresamente que <span class="uppercase">«EL ARRENDADOR»</span>, propietario del inmueble, administrador
        o representante actúan únicamente en calidad de arrendador y/o administrador y no autorizan, consienten ni participan en ninguna actividad ilícita que pudiera
        realizarse dentro del mismo. En consecuencia, <span class="uppercase">«EL ARRENDATARIO»</span> se obliga a asumir las responsabilidades que legalmente correspondan
        por los actos ilícitos que sean realizados y se obliga a mantener en paz y a salvo, en la medida permitida por la ley, a
        <span class="uppercase">«EL ARRENDADOR»</span>, propietario, administrador o representante, respecto de reclamaciones, daños, perjuicios, sanciones o procedimientos
        que deriven directamente de actos imputables a <span class="uppercase">«EL ARRENDATARIO»</span> o a las personas bajo su responsabilidad.
    </p>
    <p>
        El incumplimiento de cualquiera de las obligaciones establecidas en esta cláusula será considerado incumplimiento grave del contrato, pudiendo dar lugar a la
        terminación o rescisión del mismo. La presente cláusula no limita ni sustituye las facultades de las autoridades competentes para investigar o determinar cualquier
        responsabilidad conforme a la legislación aplicable.
    </p>

    <p>
        <strong>DÉCIMA SEGUNDA. PAGO DE SERVICIOS.</strong> Serán por cuenta de <span class="uppercase">«EL ARRENDATARIO»</span> el pago de los gastos que originen por
        concepto de energía eléctrica, agua, telefonía, cable, internet y limpieza, debiendo cubrir todo adeudo existente previo a la entrega del inmueble en la fecha de
        terminación señalada en el presente contrato.
    </p>

    <p>
        <strong>DÉCIMA TERCERA.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> no podrá subarrendar el total del inmueble o parte de él, ni traspasar sus
        derechos inquilinarios sin previa autorización por escrito del <span class="uppercase">«ARRENDADOR»</span>; asimismo requerirá autorización por escrito del
        <span class="uppercase">«ARRENDADOR»</span> para hacer mejoras útiles, necesarias o de ornato. En caso de autorización correspondiente, los gastos correrán por cuenta
        de <span class="uppercase">«EL ARRENDATARIO»</span> sin derecho a reclamo alguno al término del contrato, quedando a beneficio del inmueble cualquier obra o instalación
        realizada. De no haber autorización por parte del <span class="uppercase">«ARRENDADOR»</span>, será causa de rescisión del contrato y pagará los gastos necesarios para
        regresarlas al estado original del inmueble; renunciando así a los artículos 2297, 2298 y 2391 del Código Civil del Estado de Baja California y estando a lo dispuesto
        en el artículo 2198 del mismo ordenamiento, al firmar el contrato.
    </p>

    <p>
        <strong>DÉCIMA CUARTA. DEVOLUCIÓN DEL INMUEBLE.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> se obliga a devolver el inmueble al
        <span class="uppercase">«ARRENDADOR»</span> al término del presente contrato señalado el día <strong>{{ $ends_at }}</strong>, únicamente con el deterioro natural por el uso brindado.
    </p>
    <p>
        <strong>14.1.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> deberá entregar los recibos de los servicios mencionados en la cláusula anterior, sin adeudo alguno.
    </p>
    <p>
        <strong>14.2.</strong> El depósito de <strong>${{ $deposit_amount_formatted }}</strong> ({{ $deposit_amount_words }}) no será devuelto en su totalidad en caso de
        pérdida o daños, así como deterioro del uso mediante el arrendamiento del inmueble, servicios en deuda, limpieza profunda o incumplimiento del tiempo establecido del
        contrato, dando 15 días hábiles para la devolución del depósito. De incumplir con la fecha estimada para la devolución del inmueble se cobrará tarifa por día excedente,
        promediada al importe de la renta mensual, independiente del depósito.
    </p>
    <p>
        <strong>14.3.</strong> <span class="uppercase">«EL ARRENDATARIO»</span> tendrá la primera opción de renovar el contrato para el próximo periodo; deberá notificar con
        30 días de anticipación al <span class="uppercase">«ARRENDADOR»</span>. Se hará un incremento del 7% sobre la tarifa. De requerir factura y realizar el pago por medio
        de transferencia se cobra el IVA (8%).
    </p>

    <p>
        <strong>DÉCIMA QUINTA.</strong> En caso de que <span class="uppercase">«EL ARRENDADOR»</span> necesite vender el inmueble, reparar o modificar su construcción,
        <span class="uppercase">«EL ARRENDATARIO»</span> se obliga a desocupar el inmueble en los términos establecidos por la ley, contando el término desde su notificación por escrito.
    </p>

    <p>
        <strong>DÉCIMA SEXTA. LEGISLACIÓN APLICABLE.</strong> Para el debido cumplimiento del objeto y condiciones del presente contrato, las partes se obligan a ajustarse
        estrictamente a todas y cada una de las cláusulas del mismo, así como a los términos y procedimientos que establece el Código Civil para el Estado de Baja California,
        el Código de Procedimientos Civiles de dicha entidad y demás leyes de aplicación supletoria.
    </p>

    <p>
        <strong>DÉCIMA SÉPTIMA. JURISDICCIÓN Y COMPETENCIA.</strong> Para la interpretación y cumplimiento del objeto y condiciones del presente contrato, así como para todo
        aquello que no esté estipulado en el mismo, las partes se someten a la jurisdicción y competencia de los Tribunales del Poder Judicial de la Federación con residencia en
        la Ciudad de Ensenada, Baja California, por lo que las partes renuncian al fuero que les pudiera corresponder por sus domicilios presentes o futuros.
    </p>

    <p>
        Leídas las cláusulas por las partes y entendidas de su contenido y alcance, sin dolo existente, lesión o error, firman de conformidad el presente contrato para todos los
        efectos legales a que haya lugar en la Ciudad de Ensenada, Baja California.
    </p>

    <table class="signature-table">
        <tr>
            <td>
                <span class="uppercase">«EL ARRENDADOR»</span><br>
                <span class="signature-line">{{ $landlord_name }}</span>
            </td>
            <td>
                <span class="uppercase">«EL ARRENDATARIO»</span><br>
                <span class="signature-line">{{ $tenant_name }}</span>
            </td>
        </tr>
    </table>
</body>
</html>
